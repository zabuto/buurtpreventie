<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateRound;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class CreateRoundController
{
    public function __construct(
        private CreateRoundHandler $handler,
    ) {
    }

    #[Route('/rounds', name: 'patrol_create_round', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateRound $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input), Response::HTTP_CREATED);
    }
}
