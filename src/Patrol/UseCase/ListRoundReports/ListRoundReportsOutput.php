<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRoundReports;

final readonly class ListRoundReportsOutput
{
    /**
     * @param list<array{
     *     id: string,
     *     roundId: string,
     *     roundStartsAt: string,
     *     daypart: string,
     *     meetingPoint: string|null,
     *     walkerCount: int,
     *     outcomeId: string,
     *     outcome: string,
     *     outcomeKind: string,
     *     note: string|null,
     *     authorDisplayName: string|null,
     *     reportedAt: string,
     * }> $reports by start of the round; a null authorDisplayName is an erased member
     */
    public function __construct(
        public array $reports,
    ) {
    }
}
