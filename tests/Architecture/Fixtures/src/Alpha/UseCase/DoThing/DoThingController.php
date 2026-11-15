<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\DoThing;

use App\Alpha\UseCase\OtherThing\OtherThingHandler;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Violates the adapter rule: a controller calls the handler of another use case. */
final class DoThingController
{
    public function __construct(private DoThingHandler $handler, private OtherThingHandler $other)
    {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse([$this->handler, $this->other]);
    }
}
