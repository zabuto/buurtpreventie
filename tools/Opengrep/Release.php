<?php

declare(strict_types=1);

namespace App\Tools\Opengrep;

use DateTimeImmutable;

final readonly class Release
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public DateTimeImmutable $publishedAt,
    ) {
    }

    public function version(): string
    {
        return \sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
    }
}
