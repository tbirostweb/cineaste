<?php

namespace App\Tests\Functional;

use App\Entity\Review;
use App\Entity\User;

/**
 * Autorisations REST et GraphQL : propriété des avis, rôles, ressources d'autrui.
 */
final class AuthorizationTest extends FunctionalTestCase
{
    private function createReview(User $author, $movie, int $rating = 3): Review
    {
        $review = (new Review())->setUser($author)->setMovie($movie)->setRating($rating)->setComment('Avis');
        $this->em()->persist($review);
        $this->em()->flush();

        return $review;
    }

    private function reviewOwnerId(int $reviewId): int
    {
        $this->em()->clear();

        return $this->em()->find(Review::class, $reviewId)->getUser()->getId();
    }

    public function testAuthorCanEditButNotReassignReview(): void
    {
        $a = $this->createUser('a@example.test');
        $b = $this->createUser('b@example.test');
        $review = $this->createReview($a, $this->createMovie());
        $this->login('a@example.test');

        // Modification légitime.
        $this->request('PATCH', '/api/reviews/'.$review->getId(), ['rating' => 5, 'comment' => 'Mieux'], 'application/merge-patch+json');
        self::assertSame(200, $this->httpStatus());

        // Transfert vers B : refusé.
        $this->request('PATCH', '/api/reviews/'.$review->getId(), ['user' => $this->iri($b, 'users')], 'application/merge-patch+json');
        self::assertSame(403, $this->httpStatus());
        $this->request('PUT', '/api/reviews/'.$review->getId(), [
            'rating' => 4,
            'comment' => 'x',
            'user' => $this->iri($b, 'users'),
            'movie' => '/api/movies/'.$review->getMovie()->getId(),
        ]);
        self::assertSame(403, $this->httpStatus());

        // Déplacement vers un autre film : refusé.
        $other = $this->createMovie('Autre');
        $this->request('PATCH', '/api/reviews/'.$review->getId(), ['movie' => $this->iri($other, 'movies')], 'application/merge-patch+json');
        self::assertSame(403, $this->httpStatus());

        self::assertSame($a->getId(), $this->reviewOwnerId($review->getId()));
    }

    public function testOtherUserCannotEditOrDeleteReview(): void
    {
        $a = $this->createUser('a@example.test');
        $this->createUser('b@example.test');
        $review = $this->createReview($a, $this->createMovie());
        $this->login('b@example.test');

        $this->request('PATCH', '/api/reviews/'.$review->getId(), ['comment' => 'pirate'], 'application/merge-patch+json');
        self::assertSame(403, $this->httpStatus());
        $this->request('DELETE', '/api/reviews/'.$review->getId());
        self::assertSame(403, $this->httpStatus());
    }

    public function testCannotPostReviewForSomeoneElse(): void
    {
        $this->createUser('a@example.test');
        $b = $this->createUser('b@example.test');
        $movie = $this->createMovie();
        $this->login('a@example.test');

        $this->request('POST', '/api/reviews', ['rating' => 3, 'comment' => 'x', 'user' => $this->iri($b, 'users'), 'movie' => $this->iri($movie, 'movies')]);
        self::assertSame(403, $this->httpStatus());
    }

    public function testReviewCommentLengthAndDuplicate(): void
    {
        $a = $this->createUser('a@example.test');
        $movie = $this->createMovie();
        $this->login('a@example.test');
        $payload = ['rating' => 3, 'user' => $this->iri($a, 'users'), 'movie' => $this->iri($movie, 'movies')];

        $this->request('POST', '/api/reviews', $payload + ['comment' => str_repeat('é', Review::COMMENT_MAX_LENGTH + 1)]);
        self::assertSame(422, $this->httpStatus());

        $this->request('POST', '/api/reviews', $payload + ['comment' => str_repeat('é', Review::COMMENT_MAX_LENGTH)]);
        self::assertSame(201, $this->httpStatus());

        $this->request('POST', '/api/reviews', $payload + ['comment' => 'doublon']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testGraphQlReviewReassignmentIsDenied(): void
    {
        $a = $this->createUser('a@example.test');
        $b = $this->createUser('b@example.test');
        $review = $this->createReview($a, $this->createMovie());
        $this->login('a@example.test');

        $result = $this->request('POST', '/api/graphql', [
            'query' => 'mutation($id: ID!, $user: String!) { updateReview(input: {id: $id, user: $user}) { review { id } } }',
            'variables' => ['id' => '/api/reviews/'.$review->getId(), 'user' => $this->iri($b, 'users')],
        ], 'application/json');

        self::assertNotEmpty($result['errors'] ?? [], json_encode($result));
        self::assertSame($a->getId(), $this->reviewOwnerId($review->getId()));
    }

    public function testGraphQlUserListIsAdminOnly(): void
    {
        $this->createUser('a@example.test');
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);

        // Anonyme, en GET : aucune opération GraphQL n'est exécutée en GET.
        $this->client->request('GET', '/api/graphql', ['query' => '{ users { edges { node { email } } } }']);
        self::assertSame(405, $this->httpStatus());
        self::assertStringNotContainsString('a@example.test', $this->client->getResponse()->getContent());

        // Anonyme, en POST : refusé (pare-feu ou expression de sécurité).
        $anonymous = $this->request('POST', '/api/graphql', ['query' => '{ users { edges { node { email } } } }'], 'application/json');
        self::assertTrue(401 === $this->httpStatus() || [] !== ($anonymous['errors'] ?? []), 'La liste des comptes ne doit pas être publique en GraphQL.');
        self::assertStringNotContainsString('a@example.test', $this->client->getResponse()->getContent());

        // Utilisateur simple.
        $this->login('a@example.test');
        $user = $this->request('POST', '/api/graphql', ['query' => '{ users { edges { node { email } } } }'], 'application/json');
        self::assertNotEmpty($user['errors'] ?? []);

        // Administrateur.
        $this->logoutClient();
        $this->login('admin@example.test');
        $admin = $this->request('POST', '/api/graphql', ['query' => '{ users { edges { node { email } } } }'], 'application/json');
        self::assertEmpty($admin['errors'] ?? [], json_encode($admin));
        self::assertCount(2, $admin['data']['users']['edges']);
    }

    public function testGraphQlCatalogMutationsAreAdminOnly(): void
    {
        $this->createUser('a@example.test');
        $movie = $this->createMovie();
        $this->login('a@example.test');

        $result = $this->request('POST', '/api/graphql', [
            'query' => 'mutation($id: ID!) { deleteMovie(input: {id: $id}) { movie { id } } }',
            'variables' => ['id' => '/api/movies/'.$movie->getId()],
        ], 'application/json');
        self::assertNotEmpty($result['errors'] ?? []);

        $this->em()->clear();
        self::assertNotNull($this->em()->find(\App\Entity\Movie::class, $movie->getId()));
    }

    public function testRoleEndpointUsesAllowlist(): void
    {
        $this->createUser('admin@example.test', ['ROLE_ADMIN']);
        $target = $this->createUser('a@example.test');
        $this->login('admin@example.test');

        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ANYTHING', ''] as $role) {
            $this->request('PUT', '/api/users/'.$target->getId().'/role', ['role' => $role], 'application/json');
            self::assertSame(400, $this->httpStatus(), $role);
        }
        $this->request('PUT', '/api/users/'.$target->getId().'/role', [], 'application/json');
        self::assertSame(400, $this->httpStatus());

        $this->request('PUT', '/api/users/'.$target->getId().'/role', ['role' => 'ROLE_ADMIN'], 'application/json');
        self::assertSame(200, $this->httpStatus());
        $this->em()->clear();
        self::assertContains('ROLE_ADMIN', $this->em()->find(User::class, $target->getId())->getRoles());
    }

    public function testNonAdminCannotChangeRoles(): void
    {
        $this->createUser('a@example.test');
        $target = $this->createUser('b@example.test');
        $this->login('a@example.test');

        $this->request('PUT', '/api/users/'.$target->getId().'/role', ['role' => 'ROLE_ADMIN'], 'application/json');
        self::assertSame(403, $this->httpStatus());
    }

    public function testUserCannotReadAnotherUser(): void
    {
        $this->createUser('a@example.test');
        $b = $this->createUser('b@example.test');
        $this->login('a@example.test');

        $this->request('GET', '/api/users/'.$b->getId());
        self::assertSame(403, $this->httpStatus());
        $this->request('GET', '/api/users');
        self::assertSame(403, $this->httpStatus());
    }
}
