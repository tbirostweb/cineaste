<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Révocation des JWT par version.
 *
 * Chaque jeton porte la version de session (`tv`) de son utilisateur au moment
 * de l'émission. `User::revokeTokens()` incrémente cette version (changement de
 * mot de passe, de rôle, activation de la 2FA, incident) : tous les jetons
 * antérieurs sont alors refusés, même s'ils n'ont pas expiré.
 */
final class JwtTokenVersionListener
{
    public const CLAIM = 'tv';

    public function __construct(private readonly UserRepository $users)
    {
    }

    #[AsEventListener(event: Events::JWT_CREATED)]
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload[self::CLAIM] = $user->getTokenVersion();
        $event->setData($payload);
    }

    #[AsEventListener(event: Events::JWT_DECODED)]
    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $identifier = $payload['username'] ?? null;
        if (!\is_string($identifier)) {
            return;
        }

        $user = $this->users->findOneBy(['email' => $identifier]);
        if (!$user instanceof User) {
            $event->markAsInvalid();

            return;
        }

        // Jetons émis avant l'introduction du claim : traités comme version 0.
        $version = (int) ($payload[self::CLAIM] ?? 0);
        if ($version !== $user->getTokenVersion()) {
            $event->markAsInvalid();
        }
    }
}
