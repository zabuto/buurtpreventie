<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use Symfony\Component\Uid\Uuid;

/**
 * Who may do what with a round and what was posted on it. The voters ask this class, so the rules run in memory and
 * the authorization matrix needs no kernel.
 *
 * Roles add up and each grants only its own rights: the admin manages the catalogues but not the content of rounds,
 * and the analyst writes nothing. Being a walker of a round grants the same to every member, whatever their roles.
 */
final readonly class RoundPolicy
{
    /**
     * A walker creates a round for themselves; a coordinator for any active member, who becomes its owner.
     */
    public function mayCreate(Actor $actor, Uuid $firstWalker): bool
    {
        return $this->maySignUp($actor, $firstWalker);
    }

    /**
     * A coordinator may change or delete any round; the owner only while walking it alone, because a buddy who
     * joined must be able to count on the round.
     */
    public function mayChange(Actor $actor, Round $round): bool
    {
        if ($actor->hasRole(Role::Coordinator)) {
            return true;
        }

        return 1 === $round->walkerCount() && $round->isSignedUp($actor->memberId);
    }

    /**
     * A walker signs themselves up; a coordinator signs up any active member, also one without the walker role.
     */
    public function maySignUp(Actor $actor, Uuid $memberId): bool
    {
        if ($actor->is($memberId)) {
            return $actor->hasRole(Role::Walker);
        }

        return $actor->hasRole(Role::Coordinator);
    }

    /**
     * Withdrawing someone else is how a coordinator removes a walker.
     */
    public function mayWithdraw(Actor $actor, Uuid $memberId): bool
    {
        if ($actor->is($memberId)) {
            return $actor->hasRole(Role::Walker);
        }

        return $actor->hasRole(Role::Coordinator);
    }

    public function mayReport(Actor $actor, Round $round): bool
    {
        return $round->isSignedUp($actor->memberId) || $actor->hasRole(Role::Coordinator);
    }

    /**
     * Records that a started round was not walked, such as after a no-show.
     */
    public function mayCancel(Actor $actor, Round $round): bool
    {
        return $round->isSignedUp($actor->memberId) || $actor->hasRole(Role::Coordinator);
    }

    /**
     * Also before the start, so walkers can agree on details.
     */
    public function mayComment(Actor $actor, Round $round): bool
    {
        return $round->isSignedUp($actor->memberId) || $actor->hasRole(Role::Coordinator);
    }

    public function mayDeleteReport(Actor $actor, RoundReport $report): bool
    {
        return $actor->is($report->authorId()) || $actor->hasRole(Role::Coordinator);
    }

    public function mayDeleteComment(Actor $actor, RoundComment $comment): bool
    {
        return $actor->is($comment->authorId()) || $actor->hasRole(Role::Coordinator);
    }

    /**
     * Meeting points and outcomes.
     */
    public function mayManageCatalogue(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }

    /**
     * Part of managing members: deactivating or erasing a member takes them out of every round that has not started.
     */
    public function mayWithdrawFromUpcomingRounds(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }

    /**
     * Walkers know each other by display name; whoever arranges rounds or members needs the full name.
     *
     * @param Actor|null $actor null for the system
     */
    public function maySeeFullNames(?Actor $actor): bool
    {
        return null === $actor || $actor->hasRole(Role::Coordinator) || $actor->hasRole(Role::Admin);
    }

    /**
     * The phone numbers of walkers who consent to sharing them, for the walkers of the round and coordinators.
     */
    public function maySeeWalkerPhones(Actor $actor, Round $round): bool
    {
        return $round->isSignedUp($actor->memberId) || $actor->hasRole(Role::Coordinator);
    }
}
