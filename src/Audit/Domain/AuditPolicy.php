<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;

/**
 * The audit log is append-only: the one change is wiping the name of an erased member, part of erasing. A coordinator
 * reads the history of a round.
 */
final readonly class AuditPolicy
{
    public function mayEraseActorName(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }

    /**
     * Who planned, joined, left, reported on or commented on a round: what a coordinator needs to arrange rounds.
     */
    public function mayViewRoundHistory(Actor $actor): bool
    {
        return $actor->hasRole(Role::Coordinator);
    }
}
