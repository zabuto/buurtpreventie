<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ReportRoundResult;

use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The one report of a walked round, by a walker of the round or a coordinator.
 */
#[WriteAction(authorization: 'report.submit', audit: 'report.submitted')]
final readonly class ReportRoundResultHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private OutcomeRepository $outcomes,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
        private ExecutionContext $executionContext,
    ) {
    }

    public function __invoke(ReportRoundResult $input): ReportRoundResultOutput
    {
        $actor = $this->executionContext->actor() ?? throw AccessDenied::forAction('report.submit');
        $authorId = $actor->memberId;

        return $this->transaction->run(function () use ($input, $authorId): ReportRoundResultOutput {
            $round = $this->rounds->get(Uuid::fromString($input->roundId));
            $this->authorization->denyUnlessGranted('report.submit', $round);

            $outcome = $this->outcomes->get(Uuid::fromString($input->outcomeId));
            $report = $round->submitReport($authorId, $outcome, $input->note, $this->clock->now());
            $this->auditTrail->record(new AuditedAction('report.submitted', 'round', $round->id(), [
                'report' => ['old' => null, 'new' => $report->id()->toRfc4122()],
                'outcome' => ['old' => null, 'new' => $outcome->id()->toRfc4122()],
            ]));

            return new ReportRoundResultOutput($report->id()->toRfc4122());
        });
    }
}
