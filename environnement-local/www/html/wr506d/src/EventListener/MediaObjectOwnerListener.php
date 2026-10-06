<?php

namespace App\EventListener;

use App\Entity\MediaObject;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Enregistre le compte qui envoie un média : sert au contrôle de propriété de
 * User::$photo et au nettoyage des médias à la suppression du compte.
 */
#[AsEntityListener(event: Events::prePersist, entity: MediaObject::class)]
final class MediaObjectOwnerListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function prePersist(MediaObject $media): void
    {
        $user = $this->security->getUser();
        if (null === $media->getOwner() && $user instanceof User) {
            $media->setOwner($user);
        }
    }
}
