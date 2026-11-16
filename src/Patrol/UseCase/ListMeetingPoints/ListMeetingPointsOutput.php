<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMeetingPoints;

final readonly class ListMeetingPointsOutput
{
    /**
     * @param list<array{id: string, name: string, latitude: float|null, longitude: float|null, archived: bool}> $meetingPoints by name
     */
    public function __construct(
        public array $meetingPoints,
    ) {
    }
}
