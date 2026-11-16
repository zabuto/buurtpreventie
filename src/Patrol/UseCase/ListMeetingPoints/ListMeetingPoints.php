<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMeetingPoints;

final readonly class ListMeetingPoints
{
    /**
     * @param bool $withArchived true for whoever manages the catalogue; false gives what a member can choose from
     */
    public function __construct(
        public bool $withArchived = false,
    ) {
    }
}
