<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\GetRound;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class GetRoundController
{
    public function __construct(
        private GetRoundHandler $handler,
    ) {
    }

    #[Route('/rounds/{roundId}', name: 'patrol_get_round', requirements: ['roundId' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $roundId): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetRound($roundId)));
    }
}
