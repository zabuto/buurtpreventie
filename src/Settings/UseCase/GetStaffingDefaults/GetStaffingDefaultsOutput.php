<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetStaffingDefaults;

final readonly class GetStaffingDefaultsOutput
{
    public function __construct(
        public int $minimumWalkers,
        public int $maximumWalkers,
    ) {
    }
}
