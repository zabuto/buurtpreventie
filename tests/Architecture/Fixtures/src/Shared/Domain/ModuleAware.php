<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Alpha\UseCase\OtherThing\OtherThingHandler;

/** Violates the shared rule: Shared depends on a module. */
final class ModuleAware
{
    public function __construct(public OtherThingHandler $handler)
    {
    }
}
