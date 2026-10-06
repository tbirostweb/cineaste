<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Modification et suppression d'un compte.
 *
 * Un compte agit sur lui-même. Un administrateur agit sur les autres comptes,
 * sauf sur un compte de rang strictement supérieur au sien : un ROLE_ADMIN ne
 * touche pas un ROLE_SUPER_ADMIN.
 *
 * @extends Voter<string, User>
 */
final class UserVoter extends Voter
{
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User) {
            return false;
        }

        if (null !== $actor->getId() && $actor->getId() === $subject->getId()) {
            return true;
        }

        $actorRank = self::rank($actor);

        return $actorRank >= 2 && $actorRank >= self::rank($subject);
    }

    /** 1 : utilisateur, 2 : administrateur, 3 : super-administrateur. */
    public static function rank(User $user): int
    {
        $roles = $user->getRoles();

        return match (true) {
            \in_array('ROLE_SUPER_ADMIN', $roles, true) => 3,
            \in_array('ROLE_ADMIN', $roles, true) => 2,
            default => 1,
        };
    }
}
