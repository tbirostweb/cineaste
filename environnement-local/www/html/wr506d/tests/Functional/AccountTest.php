<?php

namespace App\Tests\Functional;

use App\Entity\User;

/**
 * Inscription et politique de mot de passe côté serveur.
 */
final class AccountTest extends FunctionalTestCase
{
    private function register(array $overrides = []): array
    {
        return $this->request('POST', '/api/users', $overrides + [
            'email' => 'new@example.test',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'plainPassword' => 'Une-phrase-longue-2026',
        ]);
    }

    public function testShortPasswordIsRejected(): void
    {
        $this->register(['plainPassword' => 'court1']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testMissingPasswordIsRejectedWithoutServerError(): void
    {
        $this->request('POST', '/api/users', ['email' => 'x@example.test', 'firstname' => 'A', 'lastname' => 'B']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testValidRegistrationHashesPasswordAndAllowsLogin(): void
    {
        $body = $this->register();
        self::assertSame(201, $this->httpStatus(), json_encode($body));
        self::assertArrayNotHasKey('plainPassword', $body);
        self::assertArrayNotHasKey('password', $body);

        $this->em()->clear();
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'new@example.test']);
        self::assertNotSame('Une-phrase-longue-2026', $user->getPassword());

        $this->login('new@example.test', 'Une-phrase-longue-2026');
        self::assertSame(200, $this->httpStatus());
    }

    public function testDuplicateEmailIsA422NotA500(): void
    {
        $this->createUser('new@example.test');
        $this->register();
        self::assertSame(422, $this->httpStatus());
    }

    public function testPasswordChangeRevokesExistingSessions(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');
        $oldToken = $this->sessionCookie()->getValue();

        $this->request('PATCH', '/api/users/'.$user->getId(), ['plainPassword' => 'Nouvelle-phrase-2026!'], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());

        $this->logoutClient();
        $this->request('GET', '/api/me', headers: ['Authorization' => 'Bearer '.$oldToken]);
        self::assertSame(401, $this->httpStatus());
    }

    public function testLoginIsThrottled(): void
    {
        $this->createUser('a@example.test');
        for ($i = 0; $i < 5; ++$i) {
            $this->login('a@example.test', 'mauvais-mot-de-passe');
            self::assertSame(401, $this->httpStatus());
        }
        $this->login('a@example.test', 'mauvais-mot-de-passe');
        self::assertContains($this->httpStatus(), [401, 429]);
        // Même le bon mot de passe est refusé pendant le blocage.
        $this->login('a@example.test');
        self::assertNotSame(200, $this->httpStatus());
    }
}
