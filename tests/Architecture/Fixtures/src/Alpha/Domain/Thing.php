<?php

declare(strict_types=1);

namespace App\Alpha\Domain;

use App\Alpha\Infrastructure\ThingStore;

/** Violates the domain rule: domain code depends on infrastructure. */
final class Thing
{
    public function __construct(public ThingStore $store)
    {
    }
}
