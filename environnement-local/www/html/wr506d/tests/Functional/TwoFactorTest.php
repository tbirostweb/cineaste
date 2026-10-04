<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Service\TwoFactorService;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Double authentification (audit : reset par GET, désactivation avant
 * confirmation, rejeu, quota, jeton intermédiaire).
 */
final class TwoFactorTest extends FunctionalTestCase
{
    use ClockSensitiveTrait;

    private function code(string $secret): string
    {
        return static::getContainer()->get(TwoFactorService::class)->currentCode($secret);
    }

    private function reloadUser(int $id): User
    {
        $this->em()->clear();

        return $this->em()->find(User::class, $id);
    }

    /** Active la 2FA via l'API ; renvoie [secret, codes de secours]. */
    private function enrol(string $email): array
    {
        $this->login($email);
        $setup = $this->request('POST', '/api/2fa/setup');
        self::assertSame(200, $this->httpStatus());
        $enabled = $this->request('POST', '/api/2fa/enable', ['code' => $this->code($setup['secret'])], 'application/json');
        self::assertSame(200, $this->httpStatus(), json_encode($enabled));

        return [$setup['secret'], $enabled['backup_codes']];
    }

    public function testSetupRejectsGet(): void
    {
        $this->createUser('a@example.test');
        $this->login('a@example.test');

        $this->request('GET', '/api/2fa/setup');
        self::assertSame(405, $this->httpStatus());
    }

    public function testUnconfirmedSetupKeepsExistingFactor(): void
    {
        static::mockTime('2026-10-04 12:00:00');
        $user = $this->createUser('a@example.test');
        [$secret, $backupCodes] = $this->enrol('a@example.test');

        // Re-setup sans code du facteur actuel : refusé, rien ne change.
        static::mockTime('+60 seconds');
        $this->request('POST', '/api/2fa/setup');
        self::assertSame(403, $this->httpStatus());

        // Re-setup avec code actuel : un secret en attente est créé, mais la 2FA
        // reste active avec l'ancien secret et les anciens codes de secours.
        $this->request('POST', '/api/2fa/setup', ['code' => $this->code($secret)], 'application/json');
        self::assertSame(200, $this->httpStatus());

        $reloaded = $this->reloadUser($user->getId());
        self::assertTrue($reloaded->isTwoFactorEnabled());
        self::assertSame($secret, $reloaded->getTwoFactorSecret());
        self::assertNotNull($reloaded->getTwoFactorPendingSecret());
        self::assertCount(\count($backupCodes), $reloaded->getTwoFactorBackupCodes());

        // L'ancien facteur permet toujours la connexion.
        $this->logoutClient();
        static::mockTime('+60 seconds');
        $pending = $this->login('a@example.test');
        self::assertTrue($pending['2fa_required']);
        $this->request('POST', '/api/2fa/login/verify', ['code' => $this->code($secret)], 'application/json', headers: ['Authorization' => 'Bearer '.$pending['token']]);
        self::assertSame(200, $this->httpStatus());
    }

    public function testOldSecretInvalidOnlyAfterConfirmation(): void
    {
        static::mockTime('2026-10-04 12:00:00');
        $user = $this->createUser('a@example.test');
        [$oldSecret] = $this->enrol('a@example.test');

        static::mockTime('+60 seconds');
        $setup = $this->request('POST', '/api/2fa/setup', ['code' => $this->code($oldSecret)], 'application/json');
        $newSecret = $setup['secret'];
        self::assertNotSame($oldSecret, $newSecret);

        static::mockTime('+60 seconds');
        $this->request('POST', '/api/2fa/enable', ['code' => $this->code($newSecret)], 'application/json');
        self::assertSame(200, $this->httpStatus());

        $reloaded = $this->reloadUser($user->getId());
        self::assertSame($newSecret, $reloaded->getTwoFactorSecret());
        self::assertNull($reloaded->getTwoFactorPendingSecret());

        // Connexion : l'ancien secret est refusé, le nouveau accepté.
        $this->logoutClient();
        static::mockTime('+60 seconds');
        $pending = $this->login('a@example.test');
        $auth = ['Authorization' => 'Bearer '.$pending['token']];
        $this->request('POST', '/api/2fa/login/verify', ['code' => $this->code($oldSecret)], 'application/json', headers: $auth);
        self::assertSame(400, $this->httpStatus());
        $this->request('POST', '/api/2fa/login/verify', ['code' => $this->code($newSecret)], 'application/json', headers: $auth);
        self::assertSame(200, $this->httpStatus());
    }

    public function testPendingTokenOnlyWorksOnVerifyRouteAndIsSingleUse(): void
    {
        static::mockTime('2026-10-04 12:00:00');
        $this->createUser('a@example.test');
        [$secret] = $this->enrol('a@example.test');
        $this->logoutClient();

        $pending = $this->login('a@example.test');
        self::assertTrue($pending['2fa_required']);
        self::assertNull($this->sessionCookie(), 'Aucune session avant le second facteur.');
        $auth = ['Authorization' => 'Bearer '.$pending['token']];

        $this->request('GET', '/api/me', headers: $auth);
        self::assertSame(403, $this->httpStatus());

        static::mockTime('+60 seconds');
        $code = $this->code($secret);
        $this->request('POST', '/api/2fa/login/verify', ['code' => $code], 'application/json', headers: $auth);
        self::assertSame(200, $this->httpStatus());
        self::assertNotNull($this->sessionCookie());

        // Rejeu du même jeton intermédiaire (et du même code) : refusé.
        $this->logoutClient();
        $this->request('POST', '/api/2fa/login/verify', ['code' => $code], 'application/json', headers: $auth);
        self::assertSame(401, $this->httpStatus());
    }

    public function testTotpCodeCannotBeReplayed(): void
    {
        static::mockTime('2026-10-04 12:00:00');
        $this->createUser('a@example.test');
        [$secret] = $this->enrol('a@example.test');
        $this->logoutClient();

        static::mockTime('+60 seconds');
        $code = $this->code($secret);
        $first = $this->login('a@example.test');
        $this->request('POST', '/api/2fa/login/verify', ['code' => $code], 'application/json', headers: ['Authorization' => 'Bearer '.$first['token']]);
        self::assertSame(200, $this->httpStatus());

        $this->logoutClient();
        $second = $this->login('a@example.test');
        $this->request('POST', '/api/2fa/login/verify', ['code' => $code], 'application/json', headers: ['Authorization' => 'Bearer '.$second['token']]);
        self::assertSame(400, $this->httpStatus(), 'Un code TOTP déjà utilisé doit être refusé.');
    }

    public function testBackupCodeIsSingleUse(): void
    {
        static::mockTime('2026-10-04 12:00:00');
        $this->createUser('a@example.test');
        [, $backupCodes] = $this->enrol('a@example.test');
        $this->logoutClient();

        $first = $this->login('a@example.test');
        $this->request('POST', '/api/2fa/login/verify', ['code' => $backupCodes[0]], 'application/json', headers: ['Authorization' => 'Bearer '.$first['token']]);
        self::assertSame(200, $this->httpStatus());

        $this->logoutClient();
        $second = $this->login('a@example.test');
        $this->request('POST', '/api/2fa/login/verify', ['code' => $backupCodes[0]], 'application/json', headers: ['Authorization' => 'Bearer '.$second['token']]);
        self::assertSame(400, $this->httpStatus());
    }

    public function testVerificationQuota(): void
    {
        static::mockTime('2026-10-04 12:00:00');
        $this->createUser('a@example.test');
        $this->enrol('a@example.test');
        $this->logoutClient();
        $this->client->getContainer()->get('cache.rate_limiter')->clear();

        $pending = $this->login('a@example.test');
        $auth = ['Authorization' => 'Bearer '.$pending['token']];
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/2fa/login/verify', ['code' => '000000'], 'application/json', headers: $auth);
            self::assertSame(400, $this->httpStatus());
        }
        $this->request('POST', '/api/2fa/login/verify', ['code' => '000000'], 'application/json', headers: $auth);
        self::assertSame(429, $this->httpStatus());
    }
}
