<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AbstractController
{
    /**
     * Rôles qu'un administrateur peut attribuer. Toute autre valeur (dont
     * ROLE_SUPER_ADMIN ou une chaîne arbitraire) est refusée : l'endpoint
     * écrivait jusqu'ici n'importe quel rôle fourni dans la charge.
     */
    public const ASSIGNABLE_ROLES = ['ROLE_USER', 'ROLE_ADMIN'];

    #[Route('/api/users/{id}/role', name: 'update_user_role', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateRole(
        int $id,
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $userRepo->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newRole = \is_array($data) ? ($data['role'] ?? null) : null;

        if (!\is_string($newRole) || !\in_array($newRole, self::ASSIGNABLE_ROLES, true)) {
            return new JsonResponse([
                'error' => 'invalid_role',
                'message' => 'Rôle non autorisé.',
                'allowed' => self::ASSIGNABLE_ROLES,
            ], 400);
        }

        // Un super-administrateur ne peut pas être rétrogradé par un simple admin.
        if (\in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['error' => 'forbidden', 'message' => 'Action réservée.'], 403);
        }

        // Un administrateur ne se retire pas lui-même ses droits par erreur.
        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $user->getId() && 'ROLE_ADMIN' !== $newRole) {
            return new JsonResponse([
                'error' => 'self_demotion',
                'message' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.',
            ], 400);
        }

        // Un seul rôle : le plus fort. Les jetons existants portent l'ancien
        // rôle : ils sont révoqués.
        $user->setRoles([$newRole]);
        $user->revokeTokens();
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'roles' => [$newRole],
            'message' => 'Rôle mis à jour avec succès'
        ]);
    }
}
