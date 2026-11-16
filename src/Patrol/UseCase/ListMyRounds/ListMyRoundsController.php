<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 200,
        description: 'The rounds of the member logged in.',
        content: new OA\JsonContent(ref: new Model(type: ListMyRoundsOutput::class)),
    )]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListMyRounds $input = new ListMyRounds(),
    ): JsonResponse {
        return new JsonResponse(($this->handler)($input));
    }
}
