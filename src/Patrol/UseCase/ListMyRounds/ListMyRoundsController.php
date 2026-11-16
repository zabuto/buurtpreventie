<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListMyRoundsController
{
    public function __construct(
        private ListMyRoundsHandler $handler,
    ) {
    }

    #[Route('/me/rounds', name: 'patrol_list_my_rounds', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListMyRounds $input = new ListMyRounds(),
    ): JsonResponse {
        return new JsonResponse(($this->handler)($input));
    }
}
