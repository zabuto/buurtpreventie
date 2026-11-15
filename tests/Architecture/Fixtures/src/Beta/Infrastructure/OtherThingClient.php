<?php

declare(strict_types=1);

namespace App\Beta\Infrastructure;

use App\Alpha\UseCase\OtherThing\OtherThing;
use App\Alpha\UseCase\OtherThing\OtherThingHandler;

/** Complies: one module calls another through its use case. */
final class OtherThingClient
{
    public function __construct(private OtherThingHandler $handler)
    {
    }

    public function run(): void
    {
        ($this->handler)(new OtherThing());
    }
}
