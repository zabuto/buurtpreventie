<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateRound;

final readonly class CreateRoundOutput
{
    public function __construct(
        public string $id,
    ) {
    }
}
