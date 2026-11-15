<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use DateTimeImmutable;

interface FixtureSet
{
    /**
     * Builds the set into an empty database; every date follows from now.
     */
    public function load(DateTimeImmutable $now): void;
}
