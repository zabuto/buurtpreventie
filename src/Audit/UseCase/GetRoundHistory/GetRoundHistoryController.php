<?php

declare(strict_types=1);

namespace App\Audit\UseCase\GetRoundHistory;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 200,
        description: 'The audit entries about the round, oldest first.',
        content: new OA\JsonContent(ref: new Model(type: GetRoundHistoryOutput::class)),
    )]
    public function __invoke(string $roundId): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetRoundHistory($roundId)));
    }
}
