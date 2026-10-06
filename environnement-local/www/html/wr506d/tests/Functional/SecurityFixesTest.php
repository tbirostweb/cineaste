<?php

namespace App\Tests\Functional;

use App\Entity\MediaObject;
use App\Entity\Movie;
use App\Entity\User;
use App\Service\TwoFactorService;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Régressions de l'audit de sécurité du 2026-10-06 (F1 à F12).
 */
final class SecurityFixesTest extends FunctionalTestCase
{
    use ClockSensitiveTrait;

    /** PNG 1x1 valide. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    // ---------------------------------------------------------------- F1

    public function testGraphQlMutationOverGetIsRejectedWithoutEffect(): void
    {
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $movie = $this->createMovie();
        $this->login('admin@example.test');
        self::assertNotNull($this->sessionCookie());

        // Lien forgé : GET avec le cookie de session, sans en-tête anti-CSRF.
        $this->client->request('GET', '/api/graphql', [
            'query' => 'mutation { deleteMovie(input: {id: "/api/movies/'.$movie->getId().'"}) { movie { id } } }',
        ]);
        self::assertContains($this->httpStatus(), [403, 405]);

        $this->em()->clear();
        self::assertNotNull($this->em()->find(Movie::class, $movie->getId()));
    }

    public function testGraphQlGetIsRejectedEvenWithCsrfHeader(): void
    {
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $movie = $this->createMovie();
        $this->login('admin@example.test');

        $this->client->request('GET', '/api/graphql', [
            'query' => 'mutation { deleteMovie(input: {id: "/api/movies/'.$movie->getId().'"}) { movie { id } } }',
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertSame(405, $this->httpStatus());

        $this->em()->clear();
        self::assertNotNull($this->em()->find(Movie::class, $movie->getId()));
    }

    public function testGraphQlPostWithCookieRequiresCsrfHeader(): void
    {
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $movie = $this->createMovie();
        $this->login('admin@example.test');

        $this->request('POST', '/api/graphql', [
            'query' => 'mutation($id: ID!) { deleteMovie(input: {id: $id}) { movie { id } } }',
            'variables' => ['id' => '/api/movies/'.$movie->getId()],
        ], 'application/json', csrf: false);
        self::assertSame(403, $this->httpStatus());

        $this->em()->clear();
        self::assertNotNull($this->em()->find(Movie::class, $movie->getId()));
    }

    // ---------------------------------------------------------------- F2

    public function testSixthAnonymousSensitiveRequestIsRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/users', ['email' => 'pas-un-email']);
            self::assertSame(422, $this->httpStatus());
        }
        $this->request('POST', '/api/users', ['email' => 'pas-un-email']);
        self::assertSame(429, $this->httpStatus());
    }

    public function testAnonymousCatalogueReadsAreNotRateLimited(): void
    {
        $this->createMovie();
        for ($i = 0; $i < 20; ++$i) {
            $this->request('GET', '/api/movies');
            self::assertSame(200, $this->httpStatus());
        }
    }

    public function testHundredFirstAuthenticatedRequestIsRateLimited(): void
    {
        $this->createUser('a@example.test');
        $this->login('a@example.test');

        for ($i = 0; $i < 100; ++$i) {
            $this->request('GET', '/api/me');
            self::assertSame(200, $this->httpStatus(), 'requête '.($i + 1));
        }
        $this->request('GET', '/api/me');
        self::assertSame(429, $this->httpStatus());
    }

    public function testPerUserLimitUsesItsOwnFactory(): void
    {
        $user = $this->createUser('a@example.test');
        $user->setLimiter(3);
        $this->em()->flush();
        $this->login('a@example.test');

        for ($i = 0; $i < 3; ++$i) {
            $this->request('GET', '/api/me');
            self::assertSame(200, $this->httpStatus());
        }
        $this->request('GET', '/api/me');
        self::assertSame(429, $this->httpStatus());
    }

    public function testRegistrationHasItsOwnLimit(): void
    {
        $limiter = static::getContainer()->get('limiter.registration')->create('127.0.0.1');
        $limiter->consume(10);

        $this->request('POST', '/api/users', [
            'email' => 'new@example.test', 'firstname' => 'A', 'lastname' => 'B',
            'plainPassword' => 'Une-phrase-longue-2026',
        ]);
        self::assertSame(429, $this->httpStatus());
    }

    public function testUploadQuotaPerUser(): void
    {
        $user = $this->createUser('a@example.test');
        static::getContainer()->get('limiter.media_upload_count')->create('upload-'.$user->getId())->consume(20);
        $this->login('a@example.test');

        $this->upload();
        self::assertSame(429, $this->httpStatus());
    }

    // ----------------------------------------------------------- F3 + F5

    public function testPasswordChangeWithoutCurrentPasswordIs422(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');

        $this->request('PATCH', '/api/users/'.$user->getId(), ['plainPassword' => 'Nouvelle-phrase-2026!'], 'application/merge-patch+json');
        self::assertSame(422, $this->httpStatus());

        $this->request('PATCH', '/api/users/'.$user->getId(), ['plainPassword' => 'Nouvelle-phrase-2026!', 'currentPassword' => 'faux-mot-de-passe'], 'application/merge-patch+json');
        self::assertSame(422, $this->httpStatus());

        // Le mot de passe n'a pas changé.
        $this->logoutClient();
        $this->login('a@example.test');
        self::assertSame(200, $this->httpStatus());
    }

    public function testGraphQlPasswordChangeRequiresCurrentPassword(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');

        $result = $this->request('POST', '/api/graphql', [
            'query' => 'mutation($id: ID!) { updateUser(input: {id: $id, plainPassword: "Nouvelle-phrase-2026!"}) { user { id } } }',
            'variables' => ['id' => '/api/users/'.$user->getId()],
        ], 'application/json');
        self::assertNotEmpty($result['errors'] ?? [], json_encode($result));

        $this->logoutClient();
        $this->login('a@example.test');
        self::assertSame(200, $this->httpStatus());
    }

    public function testEmailChangeRequiresCurrentPasswordButPhotoUpdateDoesNot(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');

        $this->request('PATCH', '/api/users/'.$user->getId(), ['email' => 'pirate@example.test'], 'application/merge-patch+json');
        self::assertSame(422, $this->httpStatus());

        // Modification sans changement d'identifiants : inchangée.
        $this->request('PATCH', '/api/users/'.$user->getId(), ['firstname' => 'Ada'], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());

        $this->request('PATCH', '/api/users/'.$user->getId(), ['email' => 'b@example.test', 'currentPassword' => self::PASSWORD], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());
    }

    public function testAdminEditsAnotherAccountWithoutCurrentPassword(): void
    {
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $target = $this->createUser('a@example.test');
        $this->login('admin@example.test');

        // Écran d'administration (UserForm) : e-mail envoyé sans mot de passe.
        $this->request('PATCH', '/api/users/'.$target->getId(), [
            'firstname' => 'Ada', 'lastname' => 'L', 'email' => 'a2@example.test',
        ], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());
    }

    public function testPasswordChangeRequiresTotpWhenTwoFactorIsEnabled(): void
    {
        static::mockTime('2026-10-06 12:00:00');
        $user = $this->createUser('a@example.test');
        $secret = $this->enrol('a@example.test')[0];

        $this->request('PATCH', '/api/users/'.$user->getId(), ['plainPassword' => 'Nouvelle-phrase-2026!', 'currentPassword' => self::PASSWORD], 'application/merge-patch+json');
        self::assertSame(422, $this->httpStatus());

        static::mockTime('+60 seconds');
        $this->request('PATCH', '/api/users/'.$user->getId(), [
            'plainPassword' => 'Nouvelle-phrase-2026!',
            'currentPassword' => self::PASSWORD,
            'twoFactorCode' => $this->code($secret),
        ], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());
    }

    public function testPasswordChangeRevokesApiKey(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');
        $apiKey = $this->request('POST', '/api/me/api-key')['apiKey'] ?? null;
        self::assertSame(201, $this->httpStatus());

        $this->assertApiKeyStatus($apiKey, 200);

        $this->login('a@example.test');
        $this->request('PATCH', '/api/users/'.$user->getId(), ['plainPassword' => 'Nouvelle-phrase-2026!', 'currentPassword' => self::PASSWORD], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());

        $this->assertApiKeyStatus($apiKey, 401);
    }

    public function testTwoFactorActivationRevokesApiKey(): void
    {
        static::mockTime('2026-10-06 12:00:00');
        $this->createUser('a@example.test');
        $this->login('a@example.test');
        $apiKey = $this->request('POST', '/api/me/api-key')['apiKey'] ?? null;
        $this->assertApiKeyStatus($apiKey, 200);

        $this->enrol('a@example.test');

        $this->assertApiKeyStatus($apiKey, 401);
    }

    public function testApiKeyIsRefusedWhenSessionVersionChanges(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');
        $apiKey = $this->request('POST', '/api/me/api-key')['apiKey'] ?? null;

        $this->em()->find(User::class, $user->getId())->revokeTokens();
        $this->em()->flush();

        $this->assertApiKeyStatus($apiKey, 401);
    }

    // ---------------------------------------------------------------- F4

    public function testFiftyVerificationsEvaluateAtMostFive(): void
    {
        static::mockTime('2026-10-06 12:00:00');
        $this->createUser('a@example.test');
        $this->enrol('a@example.test');
        $this->logoutClient();
        static::getContainer()->get('cache.rate_limiter')->clear();

        $pending = $this->login('a@example.test');
        $auth = ['Authorization' => 'Bearer '.$pending['token']];
        $evaluated = 0;
        for ($i = 0; $i < 50; ++$i) {
            $this->request('POST', '/api/2fa/login/verify', ['code' => '000000'], 'application/json', headers: $auth);
            if (400 === $this->httpStatus()) {
                ++$evaluated;
            } else {
                self::assertSame(429, $this->httpStatus());
            }
        }
        self::assertSame(5, $evaluated);
    }

    /**
     * Un code de secours consommé par une requête concurrente (écriture en
     * base hors de cette requête) est relu sous verrou et refusé : l'état en
     * mémoire, périmé, n'est plus utilisé.
     */
    public function testBackupCodeConsumedConcurrentlyIsRefused(): void
    {
        static::mockTime('2026-10-06 12:00:00');
        $user = $this->createUser('a@example.test');
        [, $backupCodes] = $this->enrol('a@example.test');
        $this->logoutClient();

        $pending = $this->login('a@example.test');
        // Entité chargée dans l'EntityManager partagé de la requête de test.
        $twoFactor = $this->em()->find(User::class, $user->getId())->getTwoFactorAuth();
        self::assertNotNull($twoFactor->getBackupCodes());

        // « Autre processus » : le code est consommé directement en base.
        $remaining = array_values(array_filter(
            $twoFactor->getBackupCodes(),
            static fn (string $hash): bool => $hash !== hash('sha256', $backupCodes[0]),
        ));
        $this->em()->getConnection()->executeStatement(
            'UPDATE user_two_factor SET backup_codes = ? WHERE id = ?',
            [json_encode($remaining), $twoFactor->getId()],
        );

        $this->request('POST', '/api/2fa/login/verify', ['code' => $backupCodes[0]], 'application/json', headers: ['Authorization' => 'Bearer '.$pending['token']]);
        self::assertSame(400, $this->httpStatus());
    }

    public function testTotpTimestepUsedConcurrentlyIsRefused(): void
    {
        static::mockTime('2026-10-06 12:00:00');
        $user = $this->createUser('a@example.test');
        [$secret] = $this->enrol('a@example.test');
        $this->logoutClient();

        static::mockTime('+60 seconds');
        $pending = $this->login('a@example.test');
        $twoFactor = $this->em()->find(User::class, $user->getId())->getTwoFactorAuth();
        $this->em()->getConnection()->executeStatement(
            'UPDATE user_two_factor SET last_used_timestep = ? WHERE id = ?',
            [intdiv(\Symfony\Component\Clock\Clock::get()->now()->getTimestamp(), 30), $twoFactor->getId()],
        );

        $this->request('POST', '/api/2fa/login/verify', ['code' => $this->code($secret)], 'application/json', headers: ['Authorization' => 'Bearer '.$pending['token']]);
        self::assertSame(400, $this->httpStatus());
    }

    // ---------------------------------------------------------------- F6

    public function testAdminCannotEditOrDeleteSuperAdmin(): void
    {
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $super = $this->createUser('super@example.test', ['ROLE_SUPER_ADMIN']);
        $this->login('admin@example.test');

        $this->request('PATCH', '/api/users/'.$super->getId(), ['firstname' => 'Pirate'], 'application/merge-patch+json');
        self::assertSame(403, $this->httpStatus());

        $this->request('DELETE', '/api/users/'.$super->getId());
        self::assertSame(403, $this->httpStatus());

        $this->em()->clear();
        $reloaded = $this->em()->find(User::class, $super->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Prénom', $reloaded->getFirstname());
    }

    public function testSuperAdminCanEditAdminAndAdminCanEditUser(): void
    {
        $admin = $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $this->createUser('super@example.test', ['ROLE_SUPER_ADMIN']);
        $user = $this->createUser('a@example.test');

        $this->login('super@example.test');
        $this->request('PATCH', '/api/users/'.$admin->getId(), ['firstname' => 'Admin'], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());

        $this->logoutClient();
        $this->login('admin@example.test');
        $this->request('PATCH', '/api/users/'.$user->getId(), ['firstname' => 'Ada'], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());
    }

    public function testUserCannotEditAnotherUser(): void
    {
        $this->createUser('a@example.test');
        $b = $this->createUser('b@example.test');
        $this->login('a@example.test');

        $this->request('PATCH', '/api/users/'.$b->getId(), ['firstname' => 'Pirate'], 'application/merge-patch+json');
        self::assertSame(403, $this->httpStatus());
    }

    // ---------------------------------------------------------------- F8

    public function testMediaCollectionIsAdminOnly(): void
    {
        $this->request('GET', '/api/media_objects');
        self::assertContains($this->httpStatus(), [401, 403]);

        $this->createUser('a@example.test');
        $this->login('a@example.test');
        $this->request('GET', '/api/media_objects');
        self::assertSame(403, $this->httpStatus());

        $this->logoutClient();
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $this->login('admin@example.test');
        $this->request('GET', '/api/media_objects');
        self::assertSame(200, $this->httpStatus());
    }

    public function testCannotUseSomeoneElsesMediaAsPhoto(): void
    {
        $a = $this->createUser('a@example.test');
        $b = $this->createUser('b@example.test');
        $media = (new MediaObject())->setOwner($b);
        $this->em()->persist($media);
        $this->em()->flush();

        $this->login('a@example.test');
        $this->request('PATCH', '/api/users/'.$a->getId(), ['photo' => '/api/media_objects/'.$media->getId()], 'application/merge-patch+json');
        self::assertContains($this->httpStatus(), [200, 403]);

        $this->em()->clear();
        self::assertNull($this->em()->find(User::class, $a->getId())->getPhoto());
    }

    public function testUploadedPhotoIsOwnedAndUsable(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');

        $media = $this->upload();
        self::assertSame(201, $this->httpStatus(), json_encode($media));

        $this->request('PATCH', '/api/users/'.$user->getId(), ['photo' => '/api/media_objects/'.$media['id']], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());

        $this->em()->clear();
        $reloaded = $this->em()->find(User::class, $user->getId());
        self::assertSame($user->getId(), $reloaded->getPhoto()?->getOwner()?->getId());

        $this->deleteUploadedFile($reloaded->getPhoto());
    }

    public function testAccountDeletionRemovesPhotoAndFile(): void
    {
        $user = $this->createUser('a@example.test');
        $this->login('a@example.test');
        $media = $this->upload();
        self::assertSame(201, $this->httpStatus());
        $this->request('PATCH', '/api/users/'.$user->getId(), ['photo' => '/api/media_objects/'.$media['id']], 'application/merge-patch+json');

        $this->em()->clear();
        $entity = $this->em()->find(MediaObject::class, $media['id']);
        $path = static::getContainer()->getParameter('kernel.project_dir').'/public/media/images/'.$entity->filePath;
        self::assertFileExists($path);

        $this->request('DELETE', '/api/users/'.$user->getId());
        self::assertSame(204, $this->httpStatus());

        $this->em()->clear();
        self::assertNull($this->em()->find(MediaObject::class, $media['id']));
        self::assertFileDoesNotExist($path);
    }

    public function testAccountDeletionKeepsMediaUsedByCatalogue(): void
    {
        $user = $this->createUser('a@example.test');
        $media = (new MediaObject())->setOwner($user);
        $this->em()->persist($media);
        $movie = $this->createMovie();
        $movie->setImage($media);
        $this->em()->flush();

        $this->login('a@example.test');
        $this->request('DELETE', '/api/users/'.$user->getId());
        self::assertSame(204, $this->httpStatus());

        $this->em()->clear();
        self::assertNotNull($this->em()->find(MediaObject::class, $media->getId()));
    }

    // -------------------------------------------------------- F10

    public function testResidualRoutesAreGone(): void
    {
        foreach (['/demo', '/products', '/product/1', '/admin/', '/2fa', '/2fa_check'] as $path) {
            $this->client->request('GET', $path);
            self::assertSame(404, $this->httpStatus(), $path);
        }
    }

    // ------------------------------------------------------------ outils

    private function code(string $secret): string
    {
        return static::getContainer()->get(TwoFactorService::class)->currentCode($secret);
    }

    /** Active la 2FA via l'API (session ouverte) ; renvoie [secret, codes de secours]. */
    private function enrol(string $email): array
    {
        $this->login($email);
        $setup = $this->request('POST', '/api/2fa/setup');
        self::assertSame(200, $this->httpStatus());
        $enabled = $this->request('POST', '/api/2fa/enable', ['code' => $this->code($setup['secret'])], 'application/json');
        self::assertSame(200, $this->httpStatus(), json_encode($enabled));

        return [$setup['secret'], $enabled['backup_codes']];
    }

    private function assertApiKeyStatus(?string $apiKey, int $expected): void
    {
        self::assertNotNull($apiKey);
        $cookies = $this->client->getCookieJar()->all();
        $this->logoutClient();
        $this->request('GET', '/api/me', headers: ['X-API-KEY' => $apiKey]);
        self::assertSame($expected, $this->httpStatus());
        foreach ($cookies as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
    }

    private function upload(): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($tmp, base64_decode(self::PNG));
        $file = new UploadedFile($tmp, 'avatar.png', 'image/png', null, true);

        $this->client->request('POST', '/api/media_objects', [], ['file' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function deleteUploadedFile(?MediaObject $media): void
    {
        if (null !== $media?->filePath) {
            @unlink(static::getContainer()->getParameter('kernel.project_dir').'/public/media/images/'.$media->filePath);
        }
    }
}
