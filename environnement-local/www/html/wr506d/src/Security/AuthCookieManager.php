<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Émet et efface le cookie de session portant le JWT.
 *
 * Le jeton n'est plus remis au JavaScript du front (il était stocké dans
 * localStorage, donc lisible par n'importe quel script de l'origine) : il voyage
 * dans un cookie HttpOnly, Secure, SameSite, que le navigateur joint seul aux
 * appels de l'API. Les clients non navigateur gardent l'en-tête Authorization.
 */
final class AuthCookieManager
{
    public const COOKIE_NAME = 'BEARER';

    public function __construct(
        private readonly bool $secure,
        private readonly string $sameSite,
        private readonly int $ttl,
    ) {
    }

    public function create(string $jwt): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue($jwt)
            ->withExpires(time() + $this->ttl)
            ->withPath('/')
            ->withSecure($this->secure)
            ->withHttpOnly(true)
            ->withSameSite($this->normalizedSameSite());
    }

    public function clear(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($this->secure)
            ->withHttpOnly(true)
            ->withSameSite($this->normalizedSameSite());
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    private function normalizedSameSite(): string
    {
        $value = strtolower($this->sameSite);

        return \in_array($value, [Cookie::SAMESITE_STRICT, Cookie::SAMESITE_LAX], true)
            ? $value
            : Cookie::SAMESITE_STRICT;
    }
}
