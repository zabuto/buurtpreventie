<?php

declare(strict_types=1);

namespace App\Beta\Infrastructure;

use App\Alpha\Domain\Thing;

/** Violates the module rule: one module reaches into the domain of another. */
final class ThingReader
{
    public function __construct(public Thing $thing)
    {
    }
}
