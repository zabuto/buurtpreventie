<?php

declare(strict_types=1);

namespace App\Audit\UseCase\GetRoundHistory;

final readonly class GetRoundHistoryOutput
{
    /**
     * @param list<array{
     *     occurredAt: string,
     *     action: string,
     *     actor: string,
     *     actorId: string|null,
     *     actorName: string|null,
     *     channel: string,
     *     changes: array<string, array{old: scalar|null, new: scalar|null}>,
     * }> $entries oldest first; actor is member, removed_member (erased, without a name) or system
     */
    public function __construct(
        public array $entries,
    ) {
    }
}
