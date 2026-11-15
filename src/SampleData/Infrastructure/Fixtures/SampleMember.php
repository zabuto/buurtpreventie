<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Shared\Domain\Actor;

final readonly class SampleMember
{
    public function __construct(
        public string $id,
        public Actor $actor,
    ) {
    }
}
