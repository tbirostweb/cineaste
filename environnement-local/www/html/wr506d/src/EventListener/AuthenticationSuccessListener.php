<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\AuthCookieManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthenticationSuccessListener
{
    /** Durée de vie du jeton intermédiaire de 2FA (secondes). */
    public const PENDING_TTL = 300;

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuthCookieManager $cookieManager,
    ) {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Si le 2FA est activé pour cet utilisateur : aucun jeton de session
        // n'est émis (ni corps, ni cookie). Seul un jeton intermédiaire court,
        // utilisable uniquement sur /api/2fa/login/verify, est renvoyé.
        if ($user->isTwoFactorEnabled()) {
            $token = $this->jwtManager->createFromPayload($user, [
                '2fa_pending' => true,
                'exp' => time() + self::PENDING_TTL,
            ]);

            $event->setData([
                'token' => $token,
                '2fa_required' => true,
            ]);

            return;
        }

        // Session ouverte : le JWT part dans un cookie HttpOnly, jamais dans
        // le corps de la réponse (donc jamais dans le stockage du navigateur).
        $jwt = $data['token'] ?? null;
        if (\is_string($jwt)) {
            $event->getResponse()->headers->setCookie($this->cookieManager->create($jwt));
        }

        unset($data['token']);
        $data['session'] = self::sessionSummary($user, $this->cookieManager->ttl());
        $event->setData($data);
    }

    /**
     * Résumé non sensible destiné à l'affichage côté front (rôles, expiration).
     * L'autorisation reste décidée par l'API à chaque requête.
     *
     * @return array{roles: list<string>, expiresAt: int}
     */
    public static function sessionSummary(User $user, int $ttl): array
    {
        return [
            'roles' => array_values($user->getRoles()),
            'expiresAt' => time() + $ttl,
        ];
    }
}
