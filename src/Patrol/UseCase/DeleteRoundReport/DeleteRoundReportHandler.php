<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRoundReport;

use App\Patrol\Domain\Round\RoundReportRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'report.delete', audit: 'report.deleted')]
final readonly class DeleteRoundReportHandler
{
    public function __construct(
        private RoundReportRepository $reports,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteRoundReport $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $report = $this->reports->get(Uuid::fromString($input->reportId));
            $this->authorization->denyUnlessGranted('report.delete', $report);

            $round = $report->round();
            $round->removeReport();
            $this->auditTrail->record(new AuditedAction('report.deleted', 'round', $round->id(), [
                'report' => ['old' => $report->id()->toRfc4122(), 'new' => null],
            ]));
        });
    }
}
