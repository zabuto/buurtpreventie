<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RestoreMeetingPoint;

use App\Patrol\Domain\MeetingPointRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'catalogue.manage', audit: 'meeting_point.restored')]
final readonly class RestoreMeetingPointHandler
{
    public function __construct(
        private MeetingPointRepository $meetingPoints,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws NotFound
     */
    public function __invoke(RestoreMeetingPoint $input): void
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');

        $this->transaction->run(function () use ($input): void {
            $meetingPoint = $this->meetingPoints->get(Uuid::fromString($input->meetingPointId));
            if (!$meetingPoint->isArchived()) {
                return;
            }

            $meetingPoint->restore();
            $this->auditTrail->record(new AuditedAction('meeting_point.restored', 'meeting_point', $meetingPoint->id()));
        });
    }
}
