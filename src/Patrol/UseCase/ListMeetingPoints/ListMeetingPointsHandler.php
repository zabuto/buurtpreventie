<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMeetingPoints;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ReadAction;

/**
 * Lists the meeting points by name: the ones a member can choose for a new round, or all of them for the admin.
 */
#[ReadAction]
final readonly class ListMeetingPointsHandler
{
    public function __construct(
        private MeetingPointRepository $meetingPoints,
        private Authorization $authorization,
    ) {
    }

    public function __invoke(ListMeetingPoints $input): ListMeetingPointsOutput
    {
        $this->authorization->denyUnlessGranted($input->withArchived ? 'catalogue.manage' : 'catalogue.list');

        $meetingPoints = \array_filter(
            $this->meetingPoints->all(),
            static fn(MeetingPoint $meetingPoint): bool => $input->withArchived || !$meetingPoint->isArchived(),
        );

        return new ListMeetingPointsOutput(\array_values(\array_map(
            static fn(MeetingPoint $meetingPoint): array => [
                'id' => $meetingPoint->id()->toRfc4122(),
                'name' => $meetingPoint->name(),
                'latitude' => $meetingPoint->coordinates()?->latitude,
                'longitude' => $meetingPoint->coordinates()?->longitude,
                'archived' => $meetingPoint->isArchived(),
            ],
            $meetingPoints,
        )));
    }
}
