<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RestoreMeetingPoint;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class RestoreMeetingPointController
{
    public function __construct(
        private RestoreMeetingPointHandler $handler,
    ) {
    }

    #[Route(
        '/admin/meeting-points/{meetingPointId}/archive',
        name: 'patrol_restore_meeting_point',
        requirements: ['meetingPointId' => Requirement::UUID],
        methods: ['DELETE'],
    )]
    #[OA\Response(response: 204, description: 'Restored, also when it was not archived.')]
    public function __invoke(string $meetingPointId): Response
    {
        ($this->handler)(new RestoreMeetingPoint($meetingPointId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
