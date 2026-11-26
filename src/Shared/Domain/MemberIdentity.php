<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Who a member is to the login: the id, the roles the member has now, whether the member may log in at all, and
 * whether the admin picked the member for MCP. A deactivated or erased member is inactive.
 */
final readonly class MemberIdentity
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public Uuid $memberId,
        public array $roles,
        public bool $active,
        public bool $mcpAllowed = false,
    ) {
    }
}
