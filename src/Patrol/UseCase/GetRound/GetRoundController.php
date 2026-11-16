<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\GetRound;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 200,
        description: 'The round with its walkers, report and comments.',
        content: new OA\JsonContent(ref: new Model(type: GetRoundOutput::class)),
    )]
    public function __invoke(string $roundId): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetRound($roundId)));
    }
}
