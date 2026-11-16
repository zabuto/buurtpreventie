<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\ListThings;

use OpenApi\Generator;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Violates the adapter rule: outside its attributes, the OpenAPI library is no business of a controller. */
final class ListThingsController
{
    public function __construct(private ListThingsHandler $handler)
    {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse([($this->handler)(new ListThings()), Generator::UNDEFINED]);
    }
}
