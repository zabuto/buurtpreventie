<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMeetingPoints;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListMeetingPointsController
{
    public function __construct(
        private ListMeetingPointsHandler $handler,
    ) {
    }

    /**
     * The list for members holds what they can choose; the one under /admin/ holds the archived items too.
     */
    #[Route('/meeting-points', name: 'patrol_list_meeting_points', methods: ['GET'])]
    #[Route('/admin/meeting-points', name: 'patrol_list_all_meeting_points', defaults: ['withArchived' => true], methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The meeting points by name; under /admin/ the archived ones too.',
        content: new OA\JsonContent(ref: new Model(type: ListMeetingPointsOutput::class)),
    )]
    public function __invoke(bool $withArchived = false): JsonResponse
    {
        return new JsonResponse(($this->handler)(new ListMeetingPoints($withArchived)));
    }
}
