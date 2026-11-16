<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use DateTimeImmutable;

interface FixtureSet
{
    /**
     * Builds the set into an empty database; every date follows from now.
     *
     * @return list<string> what the developer needs to know afterwards, such as a secret made while loading
     */
    public function load(DateTimeImmutable $now): array;
}
