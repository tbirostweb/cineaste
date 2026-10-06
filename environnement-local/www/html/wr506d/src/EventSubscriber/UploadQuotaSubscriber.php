<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Quota d'envoi de fichiers par compte (nombre d'envois et volume cumulé),
 * pour qu'un compte ne puisse pas remplir le disque du serveur. Les
 * administrateurs, qui alimentent le catalogue, n'y sont pas soumis.
 */
final class UploadQuotaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $uploadCountLimiter,
        private readonly RateLimiterFactory $uploadVolumeLimiter,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (8) : le compte est connu.
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST')
            || '/api/media_objects' !== rtrim($request->getPathInfo(), '/')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $key = 'upload-'.$user->getId();
        $count = $this->uploadCountLimiter->create($key)->consume();
        if (!$count->isAccepted()) {
            $event->setResponse($this->refuse($count));

            return;
        }

        $file = $request->files->get('file');
        if ($file instanceof UploadedFile) {
            $volumeLimiter = $this->uploadVolumeLimiter->create($key);
            $kib = (int) ceil(max(0, (int) $file->getSize()) / 1024);
            // Un fichier plus gros que tout le quota consomme le quota entier.
            $probe = $volumeLimiter->consume(0);
            $volume = $volumeLimiter->consume(max(1, min($kib, $probe->getLimit())));
            if (!$volume->isAccepted()) {
                $event->setResponse($this->refuse($volume));
            }
        }
    }

    private function refuse(RateLimit $limit): JsonResponse
    {
        return new JsonResponse([
            'error' => 'upload_quota_exceeded',
            'message' => "Quota d'envoi de fichiers atteint. Réessayez plus tard.",
        ], 429, ['Retry-After' => max(1, $limit->getRetryAfter()->getTimestamp() - time())]);
    }
}
