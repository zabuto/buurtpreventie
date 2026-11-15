<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMemberSummaries;

final readonly class GetMemberSummariesOutput
{
    /**
     * @param list<array{id: string, displayName: string|null, active: bool, name?: string|null}> $members
     */
    public function __construct(
        public array $members,
    ) {
    }
}
