<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\Role;

/**
 * Keeps at least one active admin. Only an active admin counts: a deactivated one cannot log in.
 */
final readonly class LastAdminGuard
{
    public function __construct(
        private MemberRepository $members,
    ) {
    }

    /**
     * Call inside the transaction that takes the admin role away, deactivates or erases, so the lock on the admins
     * holds until the change is stored.
     *
     * @throws LastAdmin
     */
    public function refuseToLose(Member $member): void
    {
        if (!$member->isActive() || !$member->hasRole(Role::Admin)) {
            return;
        }

        foreach ($this->members->activeAdminsForUpdate() as $admin) {
            if (!$admin->id()->equals($member->id())) {
                return;
            }
        }

        throw LastAdmin::forMember($member->id());
    }
}
