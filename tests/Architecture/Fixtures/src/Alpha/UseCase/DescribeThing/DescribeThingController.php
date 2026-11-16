<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\DescribeThing;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Keeps the adapter rule: OpenAPI attributes only document the endpoint. */
final class DescribeThingController
{
    public function __construct(private DescribeThingHandler $handler)
    {
    }

    #[OA\Response(response: 200, description: 'The thing', content: new Model(type: DescribeThing::class))]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new DescribeThing()));
    }
}
