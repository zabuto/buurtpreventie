<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ArchiveOutcome;

use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'catalogue.manage', audit: 'outcome.archived')]
final readonly class ArchiveOutcomeHandler
{
    public function __construct(
        private OutcomeRepository $outcomes,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws NotFound
     */
    public function __invoke(ArchiveOutcome $input): void
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');

        $this->transaction->run(function () use ($input): void {
            $outcome = $this->outcomes->get(Uuid::fromString($input->outcomeId));
            if ($outcome->isArchived()) {
                return;
            }

            $outcome->archive();
            $this->auditTrail->record(new AuditedAction('outcome.archived', 'outcome', $outcome->id()));
        });
    }
}
