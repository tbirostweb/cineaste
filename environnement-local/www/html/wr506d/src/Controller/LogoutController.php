<?php

namespace App\Controller;

use App\Security\AuthCookieManager;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingClaimException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déconnexion : le jeton présenté est inscrit en liste de blocage jusqu'à son
 * expiration (une copie éventuelle devient inutilisable) et le cookie est
 * effacé. Toujours 204, même sans session, pour rester idempotent.
 */
final class LogoutController
{
    public function __construct(
        private readonly AuthCookieManager $cookieManager,
        private readonly TokenExtractorInterface $tokenExtractor,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly BlockedTokenManagerInterface $blockedTokens,
    ) {
    }

    #[Route('/api/logout', name: 'app_logout', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->tokenExtractor->extract($request);
        if (\is_string($token) && '' !== $token) {
            try {
                $this->blockedTokens->add($this->jwtManager->parse($token));
            } catch (JWTDecodeFailureException | MissingClaimException) {
                // Jeton déjà invalide ou antérieur au claim jti : rien à bloquer.
            }
        }

        $response = new JsonResponse(null, 204);
        $response->headers->setCookie($this->cookieManager->clear());

        return $response;
    }
}
