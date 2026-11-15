<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeMeetingPoint;

use App\Patrol\Domain\Coordinates;
use App\Patrol\Domain\MeetingPointNameTaken;
use App\Patrol\Domain\MeetingPointRepository;
use App\Patrol\Domain\UniqueCatalogueName;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'catalogue.manage', audit: 'meeting_point.changed')]
final readonly class ChangeMeetingPointHandler
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
     * @throws NotFound
     */
    public function __invoke(ChangeMeetingPoint $input): void
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');
        $coordinates = Coordinates::fromOptional($input->latitude, $input->longitude);

        $this->transaction->run(function () use ($input, $coordinates): void {
            $meetingPoint = $this->meetingPoints->get(Uuid::fromString($input->meetingPointId));
            $this->uniqueName->ensureMeetingPointNameAvailable($input->name, $meetingPoint->id());

            $old = ['name' => $meetingPoint->name(), ...$this->position($meetingPoint->coordinates())];
            $meetingPoint->rename($input->name);
            $meetingPoint->placeAt($coordinates);
            $this->meetingPoints->update($meetingPoint);
            $new = ['name' => $meetingPoint->name(), ...$this->position($meetingPoint->coordinates())];

            $changes = [];
            foreach ($new as $field => $value) {
                if ($old[$field] === $value) {
                    continue;
                }

                $changes[$field] = ['old' => $old[$field], 'new' => $value];
            }

            if ([] === $changes) {
                return;
            }

            $this->auditTrail->record(new AuditedAction('meeting_point.changed', 'meeting_point', $meetingPoint->id(), $changes));
        });
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function position(?Coordinates $coordinates): array
    {
        return ['latitude' => $coordinates?->latitude, 'longitude' => $coordinates?->longitude];
    }
}
