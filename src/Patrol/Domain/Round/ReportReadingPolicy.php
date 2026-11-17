<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;

/**
 * Who reads what was written after a round. A note or comment may be about a suspect, so it goes no further than the
 * people who need it.
 */
final readonly class ReportReadingPolicy
{
    /**
     * The note of a report and the comments of a round: the walkers of the round, analysts, coordinators and the
     * system.
     *
     * @param Actor|null $actor null for the system
     */
    public function maySeeNotes(?Actor $actor, Round $round): bool
    {
        if (null === $actor) {
            return true;
        }

        return $round->isSignedUp($actor->memberId) || $actor->hasRole(Role::Analyst) || $actor->hasRole(Role::Coordinator);
    }

    /**
     * The reports of every round and the statistics they feed, for analysis.
     */
    public function mayListReports(Actor $actor): bool
    {
        return $actor->hasRole(Role::Analyst) || $actor->hasRole(Role::Coordinator);
    }
}
