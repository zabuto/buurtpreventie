<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ListMembers;

final readonly class ListMembersOutput
{
    /**
     * @param list<array{
     *     id: string,
     *     email: string|null,
     *     name: string|null,
     *     displayName: string|null,
     *     phone: string|null,
     *     address: string|null,
     *     roles: list<string>,
     *     active: bool,
     *     certified: bool,
     *     consentContactShare: bool,
     *     notifyOpenRounds: bool,
     * }> $members by display name, deactivated members included
     */
    public function __construct(
        public array $members,
    ) {
    }
}
