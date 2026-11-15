<?php

declare(strict_types=1);

namespace App\Identity\UseCase\VerifyPassword;

final readonly class VerifyPasswordOutput
{
    public function __construct(
        public bool $valid,
    ) {
    }
}
