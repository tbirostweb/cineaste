<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_REVIEW_USER_MOVIE', columns: ['user_id', 'movie_id'])]
#[UniqueEntity(fields: ['user', 'movie'], message: 'Vous avez déjà publié un avis sur ce film.')]
#[ApiResource(
    normalizationContext: ['groups' => ['review:read']],
    denormalizationContext: ['groups' => ['review:write']],
    operations: [
        // Lecture : réservée aux comptes connectés, comme les fiches de films.
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),

        // `securityPostDenormalize` est évalué APRÈS l'hydratation : c'est le
        // seul moment où l'on peut vérifier que l'avis créé est bien signé par
        // son auteur. La propriété `user` étant dans le groupe review:write,
        // n'importe quel inscrit pouvait sinon publier un avis au nom d'autrui.
        new Post(
            security: "is_granted('ROLE_USER')",
            securityPostDenormalize: "object.getUser() == user or is_granted('ROLE_ADMIN')",
            securityPostDenormalizeMessage: "Un avis ne peut être publié qu'en votre propre nom."
        ),

        // Modification et suppression : l'auteur ou un administrateur.
        // `security` contrôle le propriétaire AVANT hydratation ; la propriété
        // `user` étant inscriptible, un auteur pouvait encore transférer son
        // avis à un autre compte (ou vers un autre film) dans la charge.
        // `securityPostDenormalize` compare donc l'état d'origine
        // (`previous_object`) et l'état hydraté : auteur et film sont figés.
        new Patch(
            security: "is_granted('ROLE_ADMIN') or object.getUser() == user",
            securityMessage: "Vous ne pouvez modifier que vos propres avis.",
            securityPostDenormalize: self::OWNER_UNCHANGED,
            securityPostDenormalizeMessage: "L'auteur et le film d'un avis ne peuvent pas être modifiés."
        ),
        new Put(
            security: "is_granted('ROLE_ADMIN') or object.getUser() == user",
            securityMessage: "Vous ne pouvez modifier que vos propres avis.",
            securityPostDenormalize: self::OWNER_UNCHANGED,
            securityPostDenormalizeMessage: "L'auteur et le film d'un avis ne peuvent pas être modifiés."
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') or object.getUser() == user",
            securityMessage: "Vous ne pouvez supprimer que vos propres avis."
        ),
    ],
    // GraphQL : mêmes règles que REST. Sans liste explicite, API Platform
    // générait des requêtes et mutations SANS aucune expression de sécurité.
    graphQlOperations: [
        new Query(security: "is_granted('ROLE_USER')"),
        new QueryCollection(security: "is_granted('ROLE_USER')"),
        new Mutation(
            name: 'create',
            security: "is_granted('ROLE_USER')",
            securityPostDenormalize: "object.getUser() == user or is_granted('ROLE_ADMIN')",
            securityPostDenormalizeMessage: "Un avis ne peut être publié qu'en votre propre nom."
        ),
        new Mutation(
            name: 'update',
            security: "is_granted('ROLE_ADMIN') or object.getUser() == user",
            securityPostDenormalize: self::OWNER_UNCHANGED,
            securityPostDenormalizeMessage: "L'auteur et le film d'un avis ne peuvent pas être modifiés."
        ),
        new DeleteMutation(
            name: 'delete',
            security: "is_granted('ROLE_ADMIN') or object.getUser() == user"
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['user' => 'exact', 'movie' => 'exact'])]
class Review
{
    /** Après hydratation d'une modification : auteur et film inchangés (sauf admin). */
    public const OWNER_UNCHANGED = "is_granted('ROLE_ADMIN') or "
        . "(previous_object.getUser() == user and object.getUser() == user "
        . "and object.getMovie() == previous_object.getMovie())";

    public const COMMENT_MAX_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['review:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['review:read', 'review:write'])]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: self::COMMENT_MAX_LENGTH,
        maxMessage: 'Le commentaire ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['review:read', 'review:write'])]
    private ?string $comment = null;

    #[ORM\Column]
    #[Groups(['review:read'])]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read', 'review:write'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Movie::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read', 'review:write'])]
    private ?Movie $movie = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getMovie(): ?Movie
    {
        return $this->movie;
    }

    public function setMovie(?Movie $movie): static
    {
        $this->movie = $movie;

        return $this;
    }
}
