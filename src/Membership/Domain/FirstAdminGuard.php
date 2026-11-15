<?php

declare(strict_types=1);

namespace App\Membership\Domain;

/**
 * Lets the system add an admin only to an installation without one. Once an active admin exists, admins appoint
 * each other; a deactivated admin cannot log in and does not count.
 */
final readonly class FirstAdminGuard
{
    public function __construct(
        private MemberRepository $members,
    ) {
    }

    /**
     * Call inside the transaction that adds the admin, so the lock on the admins holds until the admin is stored.
     *
     * @throws AdminExists
     */
    public function refuseWhileAnAdminIsActive(): void
    {
        if ([] !== $this->members->activeAdminsForUpdate()) {
            throw AdminExists::create();
        }
    }
}
