<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ExportOwnPatrolData;

final readonly class ExportOwnPatrolDataOutput
{
    /**
     * @param list<array{roundId: string, startsAt: string, daypart: string, status: string, meetingPoint: string|null, startNote: string|null, joinedAt: string|null, signedUpByCoordinator: bool, buddies: list<string|null>}> $rounds
     *     the buddies by display name only, null for an erased one
     * @param list<array{roundId: string, roundStartsAt: string, outcome: string, note: string|null, reportedAt: string}> $reports
     * @param list<array{roundId: string, text: string, postedAt: string}> $comments
     */
    public function __construct(
        public array $rounds,
        public array $reports,
        public array $comments,
    ) {
    }
}
