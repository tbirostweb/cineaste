<?php

namespace App\EventListener;

use App\Security\AuthCookieManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTFailureEventInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Un cookie de session expiré, révoqué ou altéré est effacé dans la réponse 401 :
 * sans cela, le navigateur continuerait de le présenter (le front ne peut pas
 * supprimer un cookie HttpOnly) et chaque appel, même public, échouerait.
 */
final class JwtFailureCookieListener
{
    public function __construct(private readonly AuthCookieManager $cookieManager)
    {
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onFailure(JWTFailureEventInterface $event): void
    {
        $response = $event->getResponse();
        if (null === $response) {
            return;
        }
        $request = method_exists($event, 'getRequest') ? $event->getRequest() : null;
        if (null !== $request && !$request->cookies->has(AuthCookieManager::COOKIE_NAME)) {
            return;
        }

        $response->headers->setCookie($this->cookieManager->clear());
    }
}
