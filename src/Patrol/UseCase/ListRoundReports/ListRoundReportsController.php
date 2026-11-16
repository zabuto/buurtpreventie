<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRoundReports;

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
    public function __invoke(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoundReports $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input));
    }
}
