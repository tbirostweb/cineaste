<?php

namespace App\EventSubscriber;

use App\Security\AuthCookieManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protection CSRF des requêtes authentifiées par cookie.
 *
 * Un cookie est joint automatiquement par le navigateur, y compris à une
 * requête forgée depuis un autre site (ou un autre sous-domaine du même site,
 * que SameSite ne distingue pas). On exige donc, pour toute méthode non sûre
 * portant le cookie de session sans en-tête Authorization, l'en-tête
 * `X-Requested-With: XMLHttpRequest` : un formulaire HTML ne peut pas l'ajouter
 * et un script d'une autre origine déclenche un pré-vol CORS, refusé hors des
 * origines autorisées (nelmio_cors.yaml).
 */
final class CookieCsrfSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'X-Requested-With';
    public const EXPECTED = 'XMLHttpRequest';

    public static function getSubscribedEvents(): array
    {
        // Avant le pare-feu (priorité 8) : la requête forgée n'est même pas
        // authentifiée.
        return [KernelEvents::REQUEST => ['onKernelRequest', 9]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->isMethod('OPTIONS')) {
            return;
        }

        // GraphQL : contrôlé quelle que soit la méthode (une requête GET peut
        // y porter une opération).
        $isGraphQl = GraphQlMethodSubscriber::PATH === rtrim($request->getPathInfo(), '/');
        if ($request->isMethodSafe() && !$isGraphQl) {
            return;
        }

        if (!$request->cookies->has(AuthCookieManager::COOKIE_NAME) || $request->headers->has('Authorization')) {
            return;
        }

        if (self::EXPECTED === $request->headers->get(self::HEADER)) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'csrf_header_missing',
            'message' => 'Requête refusée : en-tête anti-CSRF absent.',
        ], 403));
    }
}
