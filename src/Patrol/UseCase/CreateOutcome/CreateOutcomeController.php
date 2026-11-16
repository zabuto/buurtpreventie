<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateOutcome;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class CreateOutcomeController
{
    public function __construct(
        private CreateOutcomeHandler $handler,
    ) {
    }

    #[Route('/admin/outcomes', name: 'patrol_create_outcome', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'The id of the new outcome.',
        content: new OA\JsonContent(ref: new Model(type: CreateOutcomeOutput::class)),
    )]
    public function __invoke(#[MapRequestPayload] CreateOutcome $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input), Response::HTTP_CREATED);
    }
}
