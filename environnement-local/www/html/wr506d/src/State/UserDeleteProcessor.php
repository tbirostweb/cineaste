<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\MediaObject;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Suppression d'un compte : supprime aussi sa photo et les médias qu'il a
 * envoyés (entité et fichier, ce dernier via VichUploader), sauf s'ils servent
 * encore ailleurs (film, acteur, autre compte). Journalise l'action quand un
 * administrateur supprime le compte d'autrui.
 */
final class UserDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $actor = $this->security->getUser();
        if ($actor instanceof User && $actor->getId() !== $data->getId() && $this->security->isGranted('ROLE_ADMIN')) {
            $this->logger->notice('Action administrateur sur un compte', [
                'action' => 'user_delete',
                'admin_id' => $actor->getId(),
                'target_id' => $data->getId(),
            ]);
        }

        $medias = $this->entityManager->getRepository(MediaObject::class)->findBy(['owner' => $data]);
        if (null !== $photo = $data->getPhoto()) {
            $medias[] = $photo;
        }

        $toDelete = [];
        foreach ($medias as $media) {
            $media->setOwner(null);
            if (!$this->isUsedElsewhere($media, $data)) {
                $toDelete[spl_object_id($media)] = $media;
            }
        }

        // Les liens compte -> média et média -> compte sont rompus avant les
        // suppressions, pour éviter tout cycle de clés étrangères.
        $data->setPhoto(null);
        $this->entityManager->flush();

        foreach ($toDelete as $media) {
            $this->entityManager->remove($media);
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function isUsedElsewhere(MediaObject $media, User $user): bool
    {
        if (!$media->getActors()->isEmpty() || !$media->getMovies()->isEmpty()) {
            return true;
        }

        foreach ($media->getUsers() as $other) {
            if ($other !== $user) {
                return true;
            }
        }

        return false;
    }
}
