<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\DoThing;

use Symfony\Component\HttpFoundation\Request;

/** Violates the input and handler rule: a handler depends on HTTP. */
final class DoThingHandler
{
    public function __invoke(DoThing $input, Request $request): DoThingOutput
    {
        return new DoThingOutput();
    }
}
