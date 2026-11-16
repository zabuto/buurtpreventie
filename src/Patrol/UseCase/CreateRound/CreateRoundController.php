<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateRound;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 201,
        description: 'The id of the new round.',
        content: new OA\JsonContent(ref: new Model(type: CreateRoundOutput::class)),
    )]
    public function __invoke(#[MapRequestPayload] CreateRound $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input), Response::HTTP_CREATED);
    }
}
