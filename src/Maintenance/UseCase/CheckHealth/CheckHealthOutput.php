<?php

declare(strict_types=1);

namespace App\Maintenance\UseCase\CheckHealth;

final readonly class CheckHealthOutput
{
    public function __construct(
        public string $status,
    ) {
    }
}
