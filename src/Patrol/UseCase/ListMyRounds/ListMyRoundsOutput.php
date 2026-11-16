<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

final readonly class ListMyRoundsOutput
{
    /**
     * @param list<array{
     *     id: string,
     *     startsAt: string,
     *     daypart: string,
     *     status: string,
     *     walkerCount: int,
     *     minimumWalkers: int,
     *     maximumWalkers: int,
     *     openSpots: int,
     *     meetingPoint: string|null,
     *     reported: bool,
     * }> $rounds in the order of the view: upcoming earliest first, to_report latest first, all as asked
     */
    public function __construct(
        public array $rounds,
    ) {
    }
}
