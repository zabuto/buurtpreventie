<?php

declare(strict_types=1);

namespace App\Audit\UseCase\GetRoundHistory;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class GetRoundHistoryController
{
    public function __construct(
        private GetRoundHistoryHandler $handler,
    ) {
    }

    #[Route('/rounds/{roundId}/history', name: 'audit_get_round_history', requirements: ['roundId' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $roundId): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetRoundHistory($roundId)));
    }
}
