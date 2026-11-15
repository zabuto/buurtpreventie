<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateMeetingPoint;

use App\Patrol\Domain\Catalogue\Coordinates;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointNameTaken;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Patrol\Domain\Catalogue\UniqueCatalogueName;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use InvalidArgumentException;

#[WriteAction(authorization: 'catalogue.manage', audit: 'meeting_point.created')]
final readonly class CreateMeetingPointHandler
{
    public function __construct(
        private MeetingPointRepository $meetingPoints,
        private UniqueCatalogueName $uniqueName,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws InvalidArgumentException for one coordinate without the other, or a point off the globe
     * @throws MeetingPointNameTaken
     */
    public function __invoke(CreateMeetingPoint $input): CreateMeetingPointOutput
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');
        $coordinates = Coordinates::fromOptional($input->latitude, $input->longitude);

        return $this->transaction->run(function () use ($input, $coordinates): CreateMeetingPointOutput {
            $this->uniqueName->ensureMeetingPointNameAvailable($input->name);

            $meetingPoint = MeetingPoint::create($input->name);
            $meetingPoint->placeAt($coordinates);
            $this->meetingPoints->add($meetingPoint);
            $this->auditTrail->record(new AuditedAction(
                'meeting_point.created',
                'meeting_point',
                $meetingPoint->id(),
                $this->changes($meetingPoint->name(), $coordinates),
            ));

            return new CreateMeetingPointOutput($meetingPoint->id()->toRfc4122());
        });
    }

    /**
     * @return array<string, array{old: null, new: string|float}>
     */
    private function changes(string $name, ?Coordinates $coordinates): array
    {
        $changes = ['name' => ['old' => null, 'new' => $name]];
        if (null === $coordinates) {
            return $changes;
        }

        return [
            ...$changes,
            'latitude' => ['old' => null, 'new' => $coordinates->latitude],
            'longitude' => ['old' => null, 'new' => $coordinates->longitude],
        ];
    }
}
