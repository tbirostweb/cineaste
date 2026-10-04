<?php

namespace App\Tests\Functional;

/**
 * Session navigateur par cookie HttpOnly (audit : JWT en localStorage).
 */
final class AuthCookieTest extends FunctionalTestCase
{
    public function testLoginSetsHttpOnlySecureCookieAndNoTokenInBody(): void
    {
        $this->createUser('a@example.test');

        $body = $this->login('a@example.test');

        self::assertSame(200, $this->httpStatus());
        self::assertArrayNotHasKey('token', $body, 'Le JWT ne doit plus être exposé au JavaScript.');
        self::assertContains('ROLE_USER', $body['session']['roles']);
        self::assertGreaterThan(time(), $body['session']['expiresAt']);

        $setCookie = implode("\n", $this->client->getResponse()->headers->all('set-cookie'));
        self::assertStringContainsString('BEARER=', $setCookie);
        self::assertStringContainsStringIgnoringCase('httponly', $setCookie);
        self::assertStringContainsStringIgnoringCase('secure', $setCookie);
        self::assertStringContainsStringIgnoringCase('samesite=strict', $setCookie);
    }

    public function testCookieAuthenticatesApiCalls(): void
    {
        $this->createUser('a@example.test');
        $this->login('a@example.test');

        $me = $this->request('GET', '/api/me');
        self::assertSame(200, $this->httpStatus());
        self::assertSame('a@example.test', $me['email']);
    }

    public function testUnsafeCookieRequestWithoutCsrfHeaderIsRejected(): void
    {
        $user = $this->createUser('a@example.test');
        $movie = $this->createMovie();
        $this->login('a@example.test');

        $this->request('POST', '/api/reviews', [
            'rating' => 4,
            'comment' => 'ok',
            'user' => $this->iri($user, 'users'),
            'movie' => $this->iri($movie, 'movies'),
        ], csrf: false);
        self::assertSame(403, $this->httpStatus());

        $this->request('POST', '/api/reviews', [
            'rating' => 4,
            'comment' => 'ok',
            'user' => $this->iri($user, 'users'),
            'movie' => $this->iri($movie, 'movies'),
        ]);
        self::assertSame(201, $this->httpStatus());
    }

    public function testLogoutBlocksTokenAndClearsCookie(): void
    {
        $this->createUser('a@example.test');
        $this->login('a@example.test');
        $stolen = $this->sessionCookie()?->getValue();
        self::assertNotEmpty($stolen);

        $this->request('POST', '/api/logout');
        self::assertSame(204, $this->httpStatus());
        self::assertNull($this->sessionCookie(), 'Le cookie doit être effacé.');

        // Une copie du jeton (volée avant la déconnexion) n'ouvre plus de session.
        $this->request('GET', '/api/me', headers: ['Authorization' => 'Bearer '.$stolen]);
        self::assertSame(401, $this->httpStatus());
    }

    public function testRevokedTokenIsRejectedAndCookieCleared(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');

        $user = $this->em()->find(\App\Entity\User::class, $user->getId());
        $user->revokeTokens();
        $this->em()->flush();

        $this->request('GET', '/api/me');
        self::assertSame(401, $this->httpStatus());
        $setCookie = implode("\n", $this->client->getResponse()->headers->all('set-cookie'));
        self::assertStringContainsString('BEARER=deleted', $setCookie);
    }
}
