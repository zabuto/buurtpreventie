<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateMeetingPoint;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class CreateMeetingPointController
{
    public function __construct(
        private CreateMeetingPointHandler $handler,
    ) {
    }

    #[Route('/admin/meeting-points', name: 'patrol_create_meeting_point', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'The id of the new meeting point.',
        content: new OA\JsonContent(ref: new Model(type: CreateMeetingPointOutput::class)),
    )]
    public function __invoke(#[MapRequestPayload] CreateMeetingPoint $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input), Response::HTTP_CREATED);
    }
}
