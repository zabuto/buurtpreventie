<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\DoThing;

use Symfony\Component\HttpFoundation\Request;

/** Violates the adapter rule: a tool depends on HTTP. */
final class DoThingTool
{
    public function __construct(private DoThingHandler $handler, private Request $request)
    {
    }
}
