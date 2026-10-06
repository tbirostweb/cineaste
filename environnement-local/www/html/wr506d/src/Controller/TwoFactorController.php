<?php

namespace App\Controller;

use App\Entity\User;
use App\EventListener\AuthenticationSuccessListener;
use App\Security\AuthCookieManager;
use App\Service\TwoFactorService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingClaimException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/2fa')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $twoFactorLimiter,
        private readonly AuthCookieManager $cookieManager,
    ) {
    }

    /**
     * Étape 1 : Setup - génération d'un secret EN ATTENTE et de son QR code.
     *
     * POST uniquement (un GET pouvait être déclenché par un simple lien ou une
     * image). Le secret actif, l'état activé et les codes de secours ne sont
     * jamais modifiés ici : la bascule n'a lieu qu'après confirmation (/enable).
     * Si la 2FA est déjà active, un code valide du facteur actuel est exigé.
     */
    #[Route('/setup', name: 'app_2fa_setup', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function setup(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], 401);
        }

        $denied = $this->underAccountLock($user, function () use ($user, $request): ?JsonResponse {
            if (null !== $limited = $this->consumeQuota($user)) {
                return $limited;
            }

            if ($user->isTwoFactorEnabled()) {
                $code = $this->readCode($request);
                if ('' === $code || !$this->verifyCurrentFactor($user, $code, consumeBackup: false)) {
                    $this->recordFailure($user);

                    return $this->json([
                        'error' => 'current_code_required',
                        'message' => 'Saisissez un code valide de votre application actuelle pour la remplacer.',
                    ], 403);
                }
            }

            return null;
        });
        if (null !== $denied) {
            return $denied;
        }

        try {
            $secret = $this->twoFactorService->generateSecret();
            $user->setTwoFactorPendingSecret($secret);
            $this->entityManager->flush();

            return $this->json([
                'secret' => $secret,
                'qr_code' => $this->twoFactorService->getQrCode($user, $secret),
                'provisioning_uri' => $this->twoFactorService->getProvisioningUri($user, $secret),
                'message' => 'Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)',
            ]);
        } catch (\Throwable $e) {
            // La trace d'exception ne doit jamais sortir de l'application.
            $this->logger->error('Échec du paramétrage 2FA', ['exception' => $e]);

            return $this->json([
                'error' => "Le paramétrage de la double authentification a échoué.",
            ], 500);
        }
    }

    /**
     * Étape 2 : Enable - vérification du code du secret en attente, puis bascule
     * atomique (secret actif remplacé, nouveaux codes de secours, sessions
     * antérieures révoquées, nouvelle session émise pour l'appelant).
     */
    #[Route('/enable', name: 'app_2fa_enable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enable(Request $request, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], 401);
        }

        $backupCodes = $this->twoFactorService->generateBackupCodes();

        // Vérification et bascule sous verrou du compte, dans une même
        // transaction (atomicité et sérialisation des essais concurrents).
        $denied = $this->underAccountLock($user, function () use ($user, $request, $backupCodes): ?JsonResponse {
            if (null !== $limited = $this->consumeQuota($user)) {
                return $limited;
            }

            $pendingSecret = $user->getTwoFactorPendingSecret();
            if (!$pendingSecret) {
                return $this->json(['error' => 'Please call /api/2fa/setup first'], 400);
            }

            $code = $this->readCode($request);
            if ('' === $code) {
                return $this->json(['error' => 'Code is required'], 400);
            }

            // Le compteur anti-rejeu repart de zéro pour un nouveau secret.
            $previousTimestep = $user->getTwoFactorAuth()?->getLastUsedTimestep();
            $user->getTwoFactorAuth()?->setLastUsedTimestep(null);
            if (!$this->twoFactorService->verifyCode($user, $code, $pendingSecret)) {
                $user->getTwoFactorAuth()?->setLastUsedTimestep($previousTimestep);
                $this->recordFailure($user);

                return $this->json(['error' => 'Invalid code'], 400);
            }

            $user->setTwoFactorSecret($pendingSecret);
            $user->setTwoFactorPendingSecret(null);
            $user->setTwoFactorBackupCodes($this->twoFactorService->hashBackupCodes($backupCodes));
            $user->setTwoFactorEnabled(true);
            $user->revokeTokens();
            // La clé API émise avant l'activation est révoquée.
            $user->getApiKey()->revoke();

            return null;
        });
        if (null !== $denied) {
            return $denied;
        }

        $response = $this->json([
            'message' => '2FA enabled successfully',
            'backup_codes' => $backupCodes,
            'warning' => 'Save these backup codes in a safe place. They will not be shown again!',
            'session' => AuthenticationSuccessListener::sessionSummary($user, $this->cookieManager->ttl()),
        ]);
        $response->headers->setCookie($this->cookieManager->create($jwtManager->create($user)));

        return $response;
    }

    /**
     * Désactiver le 2FA (code TOTP ou code de secours exigé).
     */
    #[Route('/disable', name: 'app_2fa_disable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disable(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], 401);
        }

        if (!$user->isTwoFactorEnabled()) {
            return $this->json(['error' => '2FA is not enabled'], 400);
        }

        $denied = $this->underAccountLock($user, function () use ($user, $request): ?JsonResponse {
            if (null !== $limited = $this->consumeQuota($user)) {
                return $limited;
            }

            $code = $this->readCode($request);
            if ('' === $code) {
                return $this->json(['error' => 'Code is required to disable 2FA'], 400);
            }

            if (!$this->verifyCurrentFactor($user, $code, consumeBackup: false)) {
                $this->recordFailure($user);

                return $this->json(['error' => 'Invalid code'], 400);
            }

            // Désactiver et supprimer toutes les données 2FA
            $user->setTwoFactorEnabled(false);
            $user->setTwoFactorSecret(null);
            $user->setTwoFactorPendingSecret(null);
            $user->setTwoFactorBackupCodes(null);
            $user->getTwoFactorAuth()?->setLastUsedTimestep(null);

            return null;
        });
        if (null !== $denied) {
            return $denied;
        }

        return $this->json([
            'message' => '2FA disabled successfully'
        ]);
    }

    /**
     * Vérifier le statut du 2FA
     */
    #[Route('/status', name: 'app_2fa_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], 401);
        }

        return $this->json([
            'enabled' => $user->isTwoFactorEnabled(),
            'secret_configured' => $user->getTwoFactorSecret() !== null,
            'setup_pending' => $user->getTwoFactorPendingSecret() !== null,
        ]);
    }

    /**
     * Étape de vérification du login 2FA : échange le jeton intermédiaire
     * contre une session (cookie HttpOnly). Le jeton intermédiaire est bloqué
     * après usage.
     */
    #[Route('/login/verify', name: 'app_2fa_login_verify', methods: ['POST'])]
    public function verifyLogin(
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        TokenStorageInterface $tokenStorage,
        BlockedTokenManagerInterface $blockedTokens,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedException('No user.');
            }

            $payload = $jwtManager->decode($tokenStorage->getToken());
            if (!\is_array($payload) || true !== ($payload['2fa_pending'] ?? null)) {
                throw new AccessDeniedException('This is not a 2FA token.');
            }

            $denied = $this->underAccountLock($user, function () use ($user, $request): ?JsonResponse {
                if (null !== $limited = $this->consumeQuota($user)) {
                    return $limited;
                }

                $code = $this->readCode($request);
                if ('' === $code) {
                    return $this->json(['error' => 'Code is required'], 400);
                }

                if (!$this->verifyCurrentFactor($user, $code, consumeBackup: true)) {
                    $this->recordFailure($user);

                    return $this->json(['error' => 'Invalid code'], 400);
                }

                return null;
            });
            if (null !== $denied) {
                return $denied;
            }

            try {
                $blockedTokens->add($payload);
            } catch (MissingClaimException) {
                // Jeton sans jti (émis avant la liste de blocage) : il expire seul.
            }

            $response = $this->json([
                'session' => AuthenticationSuccessListener::sessionSummary($user, $this->cookieManager->ttl()),
            ]);
            $response->headers->setCookie($this->cookieManager->create($jwtManager->create($user)));

            return $response;
        } catch (AccessDeniedException) {
            return $this->json(['error' => 'Jeton de vérification invalide.'], 403);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de la vérification 2FA', ['exception' => $e]);

            return $this->json([
                'error' => 'La vérification a échoué. Réessayez de vous connecter.',
            ], 500);
        }
    }

    /**
     * Exécute contrôle du quota, vérification du code et écriture de l'état 2FA
     * sous verrou exclusif du compte (SELECT … FOR UPDATE sur `user` et
     * `user_two_factor`), dans une transaction.
     *
     * Sans ce verrou, des vérifications concurrentes lisaient toutes le quota
     * avant que la première n'enregistre son échec (plus de 5 essais évalués),
     * et un même code de secours ou pas de temps TOTP pouvait être accepté
     * deux fois. L'état est relu en base sous verrou avant toute décision.
     *
     * @template T
     * @param callable(): T $action
     * @return T
     */
    private function underAccountLock(User $user, callable $action): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($user, $action): mixed {
            $this->entityManager->refresh($user, LockMode::PESSIMISTIC_WRITE);
            if (null !== $twoFactor = $user->getTwoFactorAuth()) {
                $this->entityManager->refresh($twoFactor, LockMode::PESSIMISTIC_WRITE);
            }

            return $action();
        });
    }

    /**
     * Vérifie un code du facteur ACTIF : TOTP (anti-rejeu) ou code de secours.
     * Un code de secours n'est consommé que lors de la connexion.
     */
    private function verifyCurrentFactor(User $user, string $code, bool $consumeBackup): bool
    {
        if ($this->twoFactorService->verifyCode($user, $code)) {
            return true;
        }

        if ($this->twoFactorService->verifyBackupCode($user, $code)) {
            if ($consumeBackup) {
                $this->twoFactorService->removeBackupCode($user, $code);
            }

            return true;
        }

        return false;
    }

    /**
     * Quota dédié aux vérifications 2FA, par compte : seuls les ÉCHECS sont
     * comptés (5 par 5 minutes glissantes). Au-delà, toute vérification est
     * refusée en 429 jusqu'à libération de la fenêtre. Appelé uniquement sous
     * underAccountLock() : lecture du quota et enregistrement de l'échec ne
     * peuvent pas être entrelacés entre deux requêtes du même compte.
     */
    private function consumeQuota(User $user): ?JsonResponse
    {
        $limit = $this->limiter($user)->consume(0);
        if ($limit->getRemainingTokens() > 0) {
            return null;
        }

        return $this->json([
            'error' => 'too_many_attempts',
            'message' => 'Trop de tentatives. Réessayez plus tard.',
        ], 429, ['Retry-After' => max(1, $limit->getRetryAfter()->getTimestamp() - time())]);
    }

    private function recordFailure(User $user): void
    {
        $this->limiter($user)->consume(1);
    }

    private function limiter(User $user): \Symfony\Component\RateLimiter\LimiterInterface
    {
        return $this->twoFactorLimiter->create('2fa-'.$user->getUserIdentifier());
    }

    private function readCode(Request $request): string
    {
        $data = json_decode($request->getContent(), true);
        $code = \is_array($data) ? ($data['code'] ?? '') : '';

        return \is_scalar($code) ? trim((string) $code) : '';
    }
}
