<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\FindStaffingGaps;

final readonly class FindStaffingGapsOutput
{
    /**
     * @param list<array{
     *     id: string,
     *     startsAt: string,
     *     daypart: string,
     *     walkerCount: int,
     *     minimumWalkers: int,
     *     walkersNeeded: int,
     *     openSpots: int,
     *     meetingPoint: string|null,
     * }> $gaps
     */
    public function __construct(
        public array $gaps,
    ) {
    }
}
