<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRounds;

final readonly class ListRoundsOutput
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
     *     signedUp: bool,
     * }> $rounds by start; signedUp says whether the member asking walks the round
     */
    public function __construct(
        public array $rounds,
    ) {
    }
}
