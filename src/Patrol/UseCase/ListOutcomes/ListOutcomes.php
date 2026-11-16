<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListOutcomes;

final readonly class ListOutcomes
{
    /**
     * @param bool $withArchived true for whoever manages the catalogue; false gives what a member can choose from
     */
    public function __construct(
        public bool $withArchived = false,
    ) {
    }
}
