<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateMeetingPoint;

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
    public function __invoke(#[MapRequestPayload] CreateMeetingPoint $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input), Response::HTTP_CREATED);
    }
}
