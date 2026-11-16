<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMemberSummaries;

final readonly class GetMemberSummariesOutput
{
    /**
     * @param list<array{id: string, displayName: string|null, name?: string|null, phone?: string|null, address?: string|null}> $members
     *     active members only; phone and address only with consent
     */
    public function __construct(
        public array $members,
    ) {
    }
}
