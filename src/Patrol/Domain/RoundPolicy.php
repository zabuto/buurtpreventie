<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;

/**
 * Who may do what with a round and what was posted on it. The voters ask this class, so the rules run in memory and
 * the authorization matrix needs no kernel.
 */
final readonly class RoundPolicy
{
    /**
     * A coordinator may change or delete any round. The creator may too, but only while walking it alone: once a
     * buddy has joined, the buddy must be able to count on the round.
     */
    public function mayChange(Actor $actor, Round $round): bool
    {
        if ($actor->hasRole(Role::Coordinator)) {
            return true;
        }

        return $actor->is($round->createdBy()) && 1 === $round->walkerCount() && $round->isSignedUp($actor->memberId);
    }

    public function mayDeleteReport(Actor $actor, RoundReport $report): bool
    {
        return $actor->is($report->memberId()) || $actor->hasRole(Role::Coordinator);
    }

    public function mayDeleteComment(Actor $actor, RoundComment $comment): bool
    {
        return $actor->is($comment->authorId()) || $actor->hasRole(Role::Coordinator);
    }
}
