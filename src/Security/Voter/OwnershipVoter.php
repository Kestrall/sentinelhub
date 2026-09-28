<?php

namespace App\Security\Voter;

use App\Entity\Alert;
use App\Entity\Device;
use App\Entity\Site;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un utilisateur ne peut gérer que les sites qu'il possède, et par extension
 * les appareils et alertes rattachés à ces sites.
 *
 * @extends Voter<string, Site|Device|Alert>
 */
final class OwnershipVoter extends Voter
{
    public const MANAGE = 'MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute
            && ($subject instanceof Site || $subject instanceof Device || $subject instanceof Alert);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $site = match (true) {
            $subject instanceof Site => $subject,
            $subject instanceof Device => $subject->getSite(),
            $subject instanceof Alert => $subject->getDevice()->getSite(),
        };

        if ($site->getOwner()->getId() !== $user->getId()) {
            $vote?->addReason('Ce site appartient à un autre utilisateur.');

            return false;
        }

        return true;
    }
}
