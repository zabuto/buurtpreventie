<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RoundStatistics;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class RoundStatisticsController
{
    public function __construct(
        private RoundStatisticsHandler $handler,
    ) {
    }

    #[Route('/stats', name: 'patrol_round_statistics', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'How the rounds of the period went, per group, next to the period before.',
        content: new OA\JsonContent(ref: new Model(type: RoundStatisticsOutput::class)),
    )]
    public function __invoke(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] RoundStatistics $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input));
    }
}
