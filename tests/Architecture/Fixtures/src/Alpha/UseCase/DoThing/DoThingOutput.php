<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\DoThing;

use App\Alpha\Domain\Thing;

/** Violates the adapter rule: output exposes a domain object. */
final class DoThingOutput
{
    public ?Thing $thing = null;
}
