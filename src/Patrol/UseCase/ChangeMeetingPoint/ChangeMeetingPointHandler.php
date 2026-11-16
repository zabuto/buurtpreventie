<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeMeetingPoint;

use App\Patrol\Domain\Catalogue\Coordinates;
use App\Patrol\Domain\Catalogue\InvalidCoordinates;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointNameTaken;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Patrol\Domain\Catalogue\UniqueCatalogueName;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\Unchanged;
use App\Shared\Domain\WriteAction;
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
     * @throws InvalidCoordinates for one coordinate without the other, or a point off the globe
     * @throws MeetingPointNameTaken
     * @throws NotFound
     */
    public function __invoke(ChangeMeetingPoint $input): void
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');
        $coordinates = self::coordinates($input);

        $this->transaction->run(function () use ($input, $coordinates): void {
            $meetingPoint = $this->meetingPoints->get(Uuid::fromString($input->meetingPointId));

            $old = ['name' => $meetingPoint->name(), ...$this->position($meetingPoint->coordinates())];
            $this->rename($meetingPoint, $input->name);
            if (!$coordinates instanceof Unchanged) {
                $meetingPoint->placeAt($coordinates);
            }
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
     * @throws InvalidCoordinates for one coordinate left out and the other not
     */
    private static function coordinates(ChangeMeetingPoint $input): Coordinates|Unchanged|null
    {
        if ($input->latitude instanceof Unchanged && $input->longitude instanceof Unchanged) {
            return Unchanged::Field;
        }

        if ($input->latitude instanceof Unchanged || $input->longitude instanceof Unchanged) {
            throw new InvalidCoordinates('A meeting point takes both a latitude and a longitude, or neither.');
        }

        return Coordinates::fromOptional($input->latitude, $input->longitude);
    }

    private function rename(MeetingPoint $meetingPoint, string|Unchanged $name): void
    {
        if ($name instanceof Unchanged) {
            return;
        }

        $this->uniqueName->ensureMeetingPointNameAvailable($name, $meetingPoint->id());
        $meetingPoint->rename($name);
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function position(?Coordinates $coordinates): array
    {
        return ['latitude' => $coordinates?->latitude, 'longitude' => $coordinates?->longitude];
    }
}
