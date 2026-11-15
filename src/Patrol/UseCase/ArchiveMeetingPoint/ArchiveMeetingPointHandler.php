<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ArchiveMeetingPoint;

use App\Patrol\Domain\Catalogue\MeetingPointInUse;
use App\Patrol\Domain\Catalogue\MeetingPointInUseGuard;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'catalogue.manage', audit: 'meeting_point.archived')]
final readonly class ArchiveMeetingPointHandler
{
    public function __construct(
        private MeetingPointRepository $meetingPoints,
        private MeetingPointInUseGuard $inUseGuard,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws NotFound
     * @throws MeetingPointInUse
     */
    public function __invoke(ArchiveMeetingPoint $input): void
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');

        $this->transaction->run(function () use ($input): void {
            $meetingPoint = $this->meetingPoints->get(Uuid::fromString($input->meetingPointId));
            if ($meetingPoint->isArchived()) {
                return;
            }

            $this->inUseGuard->refuseWhileInUse($meetingPoint, $this->clock->now());

            $meetingPoint->archive();
            $this->auditTrail->record(new AuditedAction('meeting_point.archived', 'meeting_point', $meetingPoint->id()));
        });
    }
}
