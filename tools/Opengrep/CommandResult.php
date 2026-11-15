<?php

declare(strict_types=1);

namespace App\Tools\Opengrep;

final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $error,
    ) {
    }
}
