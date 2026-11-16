<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeMeetingPoint;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ChangeMeetingPointController
{
    public function __construct(
        private ChangeMeetingPointHandler $handler,
    ) {
    }

    /**
     * The meeting point comes from the path: the body needs no id, and one it carries counts for nothing.
     */
    #[Route(
        '/admin/meeting-points/{meetingPointId}',
        name: 'patrol_change_meeting_point',
        requirements: ['meetingPointId' => Requirement::UUID],
        methods: ['PATCH'],
    )]
    public function __invoke(
        string $meetingPointId,
        #[MapRequestPayload(serializationContext: ['default_constructor_arguments' => [ChangeMeetingPoint::class => ['meetingPointId' => '']]])]
        ChangeMeetingPoint $input,
    ): Response {
        ($this->handler)(new ChangeMeetingPoint($meetingPointId, $input->name, $input->latitude, $input->longitude));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
