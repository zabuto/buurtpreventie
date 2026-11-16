<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRoundReports;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListRoundReportsController
{
    public function __construct(
        private ListRoundReportsHandler $handler,
    ) {
    }

    #[Route('/reports', name: 'patrol_list_round_reports', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The reports of the rounds that start in the period, with their notes.',
        content: new OA\JsonContent(ref: new Model(type: ListRoundReportsOutput::class)),
    )]
    public function __invoke(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoundReports $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input));
    }
}
