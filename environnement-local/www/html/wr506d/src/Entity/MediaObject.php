<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['media_object:read']],
    types: ['https://schema.org/MediaObject'],
    outputFormats: ['jsonld' => ['application/ld+json']],
    operations: [
        new Get(security: "is_granted('PUBLIC_ACCESS')"),
        // La liste de tous les médias est une donnée d'administration.
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        // L'envoi de fichiers était ouvert à tout le monde : n'importe qui
        // pouvait remplir le disque du VPS depuis Internet. Il faut désormais
        // un compte — l'inscription envoie l'avatar après création du compte.
        new Post(
            security: "is_granted('ROLE_USER')",
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            )
        )
    ],
    // GraphQL : mêmes règles et mêmes groupes que REST. Sans liste explicite,
    // API Platform générait requêtes et mutations SANS expression de sécurité.
    // L'envoi de fichier reste réservé à l'opération REST multipart : aucune
    // mutation GraphQL n'est exposée (REST n'offre ni modification ni suppression).
    graphQlOperations: [
        new Query(security: "is_granted('PUBLIC_ACCESS')"),
        new QueryCollection(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class MediaObject
{
    /**
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['media_object:read', 'user:read', 'actor:read', 'movie:read'])]
    private ?int $id = null;

    #[ApiProperty(types: ['https://schema.org/contentUrl'], writable: false)]
    // Les groupes de liste ('movie:list', 'actor:list') manquaient : une image
    // imbriquée dans une collection n'était sérialisée que par son IRI, sans
    // URL exploitable. Le client ne pouvait donc rien afficher depuis une liste.
    #[Groups([
        'media_object:read',
        'user:read',
        'actor:read',
        'actor:list',
        'movie:read',
        'movie:list',
    ])]
    public ?string $contentUrl = null;

    #[Vich\UploadableField(mapping: 'media_object', fileNameProperty: 'filePath')]
    #[Assert\NotNull(message: "Aucun fichier n'a été transmis.")]
    /*
     * Sans cette contrainte, l'endpoint acceptait n'importe quel fichier
     * jusqu'à upload_max_filesize (50 Mo dans le Dockerfile) : une archive,
     * un script, un binaire. La validation applicative est le seul garde-fou,
     * PHP ne vérifie ni le type réel ni une taille métier.
     */
    #[Assert\File(
        maxSize: '4M',
        maxSizeMessage: "L'image ne doit pas dépasser {{ limit }} {{ suffix }}.",
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP ou AVIF.',
    )]
    public ?File $file = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(nullable: true)]
    public ?string $filePath = null;

    /**
     * Compte qui a envoyé le fichier (jamais exposé). Sert au contrôle de
     * propriété de User::$photo et au nettoyage à la suppression du compte.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    /**
     * @var Collection<int, Actor>
     */
    #[ORM\OneToMany(targetEntity: Actor::class, mappedBy: 'photo')]
    private Collection $actors;

    /**
     * @var Collection<int, Movie>
     */
    #[ORM\OneToMany(targetEntity: Movie::class, mappedBy: 'image')]
    private Collection $movies;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'photo')]
    private Collection $users;

    public function __construct()
    {
        $this->actors = new ArrayCollection();
        $this->movies = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, Actor>
     */
    public function getActors(): Collection
    {
        return $this->actors;
    }

    public function addActor(Actor $actor): static
    {
        if (!$this->actors->contains($actor)) {
            $this->actors->add($actor);
            $actor->setPhoto($this);
        }

        return $this;
    }

    public function removeActor(Actor $actor): static
    {
        if ($this->actors->removeElement($actor)) {
            if ($actor->getPhoto() === $this) {
                $actor->setPhoto(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Movie>
     */
    public function getMovies(): Collection
    {
        return $this->movies;
    }

    public function addMovie(Movie $movie): static
    {
        if (!$this->movies->contains($movie)) {
            $this->movies->add($movie);
            $movie->setImage($this);
        }

        return $this;
    }

    public function removeMovie(Movie $movie): static
    {
        if ($this->movies->removeElement($movie)) {
            if ($movie->getImage() === $this) {
                $movie->setImage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setPhoto($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getPhoto() === $this) {
                $user->setPhoto(null);
            }
        }

        return $this;
    }
}
