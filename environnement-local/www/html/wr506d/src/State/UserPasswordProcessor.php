<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User;
use App\Service\TwoFactorService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Hache `plainPassword` avant que Doctrine n'enregistre l'utilisateur.
 *
 * Pourquoi un processeur plutôt qu'un écouteur Doctrine : l'écouteur existant
 * n'est branché que sur `prePersist`, donc uniquement à la création. Le
 * déplacer ou l'étendre à `preUpdate` ne suffirait pas — `preUpdate` n'est
 * déclenché que si Doctrine détecte un changement sur un champ **mappé**, et
 * `plainPassword` n'en est pas un. Une requête ne modifiant que le mot de passe
 * ne produirait aucun changeset : l'écouteur ne serait jamais appelé et le
 * changement serait silencieusement perdu.
 *
 * Le processeur s'exécute avant la persistance, quelle que soit l'opération,
 * et ne dépend d'aucun calcul de changeset.
 *
 * L'écouteur `UserPasswordListener` est conservé : il couvre les créations
 * hors API (fixtures, commandes console). Les deux sont idempotents — celui
 * qui passe en second trouve `plainPassword` déjà vidé.
 *
 * Réauthentification : changer SON PROPRE mot de passe (ou son e-mail, pour un
 * compte non administrateur) exige `currentPassword`, et `twoFactorCode` si la
 * 2FA est active. Un changement de mot de passe révoque la clé API.
 */
final class UserPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Security $security,
        private readonly TwoFactorService $twoFactorService,
        #[Autowire(service: 'limiter.two_factor')]
        private readonly RateLimiterFactory $reauthLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): mixed {
        if ($data instanceof User) {
            $plainPassword = $data->getPlainPassword();
            $changesPassword = null !== $plainPassword && '' !== trim($plainPassword);

            if (null !== $data->getId()) {
                $previous = $context['previous_data'] ?? $context['graphql_context']['previous_object'] ?? null;
                $this->assertReauthenticated($data, $changesPassword, $previous);
                $this->auditAdminAction($data, 'user_update');
            }

            if ($changesPassword) {
                $data->setPassword($this->hasher->hashPassword($data, $plainPassword));

                // Changement de mot de passe d'un compte existant : les
                // sessions ouvertes avec l'ancien et la clé API sont révoquées.
                if (null !== $data->getId()) {
                    $data->revokeTokens();
                    $data->getApiKey()->revoke();
                }
            }

            // Vidé dans tous les cas : le mot de passe en clair ne doit pas
            // survivre à la requête, y compris s'il n'a pas été utilisé.
            $data->setPlainPassword(null);
            $data->setCurrentPassword(null);
            $data->setTwoFactorCode(null);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function assertReauthenticated(User $data, bool $changesPassword, mixed $previous): void
    {
        $actor = $this->security->getUser();
        if (!$actor instanceof User || $actor->getId() !== $data->getId()) {
            return;
        }

        $changesEmail = $previous instanceof User && $previous->getEmail() !== $data->getEmail();
        // L'écran d'administration renvoie l'e-mail sans mot de passe actuel :
        // un administrateur qui édite sa fiche n'est pas concerné pour l'e-mail.
        if (!$changesPassword && !($changesEmail && !$this->security->isGranted('ROLE_ADMIN'))) {
            return;
        }

        $limiter = $this->reauthLimiter->create('reauth-'.$data->getId());
        if ($limiter->consume(0)->getRemainingTokens() <= 0) {
            throw new TooManyRequestsHttpException(null, 'Trop de tentatives. Réessayez plus tard.');
        }

        $current = (string) $data->getCurrentPassword();
        // L'empreinte n'est pas encore remplacée : c'est celle du mot de passe actuel.
        if ('' === $current || !$this->hasher->isPasswordValid($data, $current)) {
            $limiter->consume(1);
            $this->fail('currentPassword', 'Le mot de passe actuel est requis et doit être correct.');
        }

        if ($data->isTwoFactorEnabled()
            && !$this->twoFactorService->verifyCode($data, trim((string) $data->getTwoFactorCode()))) {
            $limiter->consume(1);
            $this->fail('twoFactorCode', "Le code de l'application d'authentification est requis.");
        }
    }

    private function auditAdminAction(User $target, string $action): void
    {
        $actor = $this->security->getUser();
        if ($actor instanceof User && $actor->getId() !== $target->getId() && $this->security->isGranted('ROLE_ADMIN')) {
            $this->logger->notice('Action administrateur sur un compte', [
                'action' => $action,
                'admin_id' => $actor->getId(),
                'target_id' => $target->getId(),
            ]);
        }
    }

    private function fail(string $property, string $message): never
    {
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], null, $property, null),
        ]));
    }
}
