<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateOutcome;

final readonly class CreateOutcomeOutput
{
    public function __construct(
        public string $id,
    ) {
    }
}
