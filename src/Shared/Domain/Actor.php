<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * The member a policy decides for, with the roles that member has.
 */
final readonly class Actor
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public Uuid $memberId,
        public array $roles,
    ) {
    }

    public function hasRole(Role $role): bool
    {
        return \in_array($role, $this->roles, strict: true);
    }

    public function is(Uuid $memberId): bool
    {
        return $this->memberId->equals($memberId);
    }
}
