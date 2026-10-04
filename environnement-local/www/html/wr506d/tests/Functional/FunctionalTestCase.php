<?php

namespace App\Tests\Functional;

use App\Entity\Movie;
use App\Entity\User;
use App\Security\AuthCookieManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests d'intégration HTTP sur une base SQLite jetable (var/test.db), recréée
 * avant chaque test. Aucune base, aucun SMTP ni service externe réel.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected const PASSWORD = 'Correct-Horse-Battery-42';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        // Le cookie de session est Secure : les requêtes de test passent en HTTPS.
        $this->client->setServerParameter('HTTPS', 'on');

        $em = $this->em();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        foreach (['cache.rate_limiter', 'cache.app'] as $pool) {
            static::getContainer()->get($pool)->clear();
        }
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $email, array $roles = ['ROLE_USER']): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstname('Prénom')
            ->setLastname('Nom')
            ->setRoles($roles);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function createMovie(string $name = 'Film test'): Movie
    {
        $movie = new Movie();
        $movie->setName($name);
        $this->em()->persist($movie);
        $this->em()->flush();

        return $movie;
    }

    /** Requête JSON ; `$csrf` ajoute l'en-tête anti-CSRF attendu par l'API. */
    protected function request(
        string $method,
        string $uri,
        ?array $body = null,
        string $contentType = 'application/ld+json',
        bool $csrf = true,
        array $headers = [],
    ): array {
        $server = ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/ld+json'];
        if ($csrf) {
            $server['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request($method, $uri, [], [], $server, null === $body ? null : json_encode($body));
        $content = $this->client->getResponse()->getContent();
        $decoded = json_decode((string) $content, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /** Connexion par mot de passe ; renvoie le corps de /auth. */
    protected function login(string $email, string $password = self::PASSWORD): array
    {
        return $this->request('POST', '/auth', ['email' => $email, 'password' => $password], 'application/json');
    }

    protected function sessionCookie(): ?Cookie
    {
        return $this->client->getCookieJar()->get(AuthCookieManager::COOKIE_NAME);
    }

    protected function logoutClient(): void
    {
        $this->client->getCookieJar()->clear();
    }

    protected function httpStatus(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    protected function iri(object $entity, string $collection): string
    {
        return '/api/'.$collection.'/'.$entity->getId();
    }
}
