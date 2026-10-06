<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * GraphQL n'exécute une opération qu'en POST.
 *
 * API Platform exécute aussi `?query=` en GET, mutations comprises. Un simple
 * lien ou une balise <img> suffisait alors à déclencher une mutation avec le
 * cookie de session de la victime (CSRF), le contrôle anti-CSRF ignorant les
 * méthodes sûres. Seul un GET sans paramètre `query` reste admis : il n'exécute
 * rien et sert l'IDE GraphiQL en développement.
 */
final class GraphQlMethodSubscriber implements EventSubscriberInterface
{
    public const ROUTE = 'api_graphql_entrypoint';
    public const PATH = '/api/graphql';

    public static function getSubscribedEvents(): array
    {
        // Après le routeur (32), avant le contrôle CSRF (9) et le pare-feu (8).
        return [KernelEvents::REQUEST => ['onKernelRequest', 10]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (self::ROUTE !== $request->attributes->get('_route') && self::PATH !== rtrim($request->getPathInfo(), '/')) {
            return;
        }

        if ($request->isMethod('POST') || $request->isMethod('OPTIONS')) {
            return;
        }

        if ($request->isMethod('GET') && !$request->query->has('query')) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'method_not_allowed',
            'message' => 'Les opérations GraphQL doivent être envoyées en POST.',
        ], 405, ['Allow' => 'POST']));
    }
}
