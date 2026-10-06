<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Entity\User;

/**
 * Limitation de débit de l'API.
 *
 * - Anonyme (par IP) : seules les requêtes non sûres sont comptées. Les GET du
 *   catalogue public restent libres, 5 par minute les auraient rendus
 *   inutilisables.
 * - Authentifié (par compte) : toutes les requêtes, au plafond propre au compte
 *   (`User::$limiter`, 100 par défaut) via une factory dédiée à cette valeur.
 * - Inscription (POST /api/users, par IP) : quota supplémentaire sur l'heure.
 *
 * L'acceptation est décidée par `RateLimit::isAccepted()`. Le calcul précédent
 * (`limit - remaining`) ne dépassait jamais la limite : tout passait.
 */
final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    /** @var array<int, RateLimiterFactory> */
    private array $perUserFactories = [];

    public function __construct(
        private readonly RateLimiterFactory $anonymousApiLimiter,
        private readonly RateLimiterFactory $authenticatedApiLimiter,
        private readonly RateLimiterFactory $registrationLimiter,
        private readonly StorageInterface $rateLimiterStorage,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Ne pas limiter les requêtes OPTIONS (Preflight CORS)
        if ($request->isMethod('OPTIONS')) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), '/api/') ||
            str_starts_with($request->getPathInfo(), '/api/docs')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        $isAuthenticated = $user instanceof User;
        $clientIp = $request->getClientIp() ?? 'unknown';

        if ($isAuthenticated) {
            $limit = $this->userFactory($user)->create($user->getUserIdentifier())->consume();
        } elseif ($request->isMethodSafe()) {
            return;
        } else {
            $limit = $this->anonymousApiLimiter->create($clientIp)->consume();
        }

        $request->attributes->set('_rate_limit', [
            'limit' => $limit->getLimit(),
            'remaining' => $limit->getRemainingTokens(),
            'reset' => $limit->getRetryAfter()->getTimestamp(),
        ]);

        if (!$limit->isAccepted()) {
            $event->setResponse($this->tooManyRequests($limit));

            return;
        }

        if ($this->isRegistration($request)) {
            $registration = $this->registrationLimiter->create($clientIp)->consume();
            if (!$registration->isAccepted()) {
                $event->setResponse($this->tooManyRequests($registration));
            }
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_rate_limit')) {
            return;
        }

        $rateLimitInfo = $request->attributes->get('_rate_limit');
        $response = $event->getResponse();

        $response->headers->add([
            'X-RateLimit-Limit' => $rateLimitInfo['limit'],
            'X-RateLimit-Remaining' => $rateLimitInfo['remaining'],
            'X-RateLimit-Reset' => $rateLimitInfo['reset'],
        ]);
    }

    /** Factory au plafond du compte ; celle de la configuration par défaut. */
    private function userFactory(User $user): RateLimiterFactory
    {
        $custom = $user->getLimiter();
        if (null === $custom || $custom < 1) {
            return $this->authenticatedApiLimiter;
        }

        return $this->perUserFactories[$custom] ??= new RateLimiterFactory([
            'id' => 'authenticated_api_'.$custom,
            'policy' => 'fixed_window',
            'limit' => $custom,
            'interval' => '1 minute',
        ], $this->rateLimiterStorage);
    }

    private function isRegistration(Request $request): bool
    {
        return $request->isMethod('POST') && '/api/users' === rtrim($request->getPathInfo(), '/');
    }

    private function tooManyRequests(RateLimit $limit): JsonResponse
    {
        $retryAfter = $limit->getRetryAfter()->getTimestamp();

        return new JsonResponse(
            ['error' => 'Too Many Requests', 'message' => 'Rate limit exceeded.'],
            429,
            [
                'Retry-After' => max(1, $retryAfter - time()),
                'X-RateLimit-Limit' => $limit->getLimit(),
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $retryAfter,
            ]
        );
    }
}
