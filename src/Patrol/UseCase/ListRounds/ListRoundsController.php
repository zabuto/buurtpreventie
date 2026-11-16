<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRounds;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListRoundsController
{
    public function __construct(
        private ListRoundsHandler $handler,
    ) {
    }

    #[Route('/rounds', name: 'patrol_list_rounds', methods: ['GET'])]
    public function __invoke(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRounds $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input));
    }
}
