<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;

/**
 * Who may manage members. The voter asks this class, so the rules run in memory.
 */
final readonly class MemberPolicy
{
    /**
     * Adding, changing, roles, (de)activating and erasing.
     */
    public function mayManage(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }

    /**
     * Consent to share contact details and notifications about open rounds are the member's own choice; nobody makes
     * it for them.
     */
    public function mayChangeOwnChoice(Actor $actor, Member $member): bool
    {
        return $actor->is($member->id());
    }

    /**
     * Having an invitation sent, which a coordinator may too, for a member whose invitation got lost.
     */
    public function mayInvite(Actor $actor): bool
    {
        return $actor->hasRole(Role::Coordinator) || $actor->hasRole(Role::Admin);
    }
}
