<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ArchiveMeetingPoint;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ArchiveMeetingPointController
{
    public function __construct(
        private ArchiveMeetingPointHandler $handler,
    ) {
    }

    #[Route(
        '/admin/meeting-points/{meetingPointId}/archive',
        name: 'patrol_archive_meeting_point',
        requirements: ['meetingPointId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[OA\Response(response: 204, description: 'Archived, also when it already was.')]
    public function __invoke(string $meetingPointId): Response
    {
        ($this->handler)(new ArchiveMeetingPoint($meetingPointId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
