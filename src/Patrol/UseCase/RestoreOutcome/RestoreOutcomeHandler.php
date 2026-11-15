<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RestoreOutcome;

use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'catalogue.manage', audit: 'outcome.restored')]
final readonly class RestoreOutcomeHandler
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
    public function __invoke(RestoreOutcome $input): void
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');

        $this->transaction->run(function () use ($input): void {
            $outcome = $this->outcomes->get(Uuid::fromString($input->outcomeId));
            if (!$outcome->isArchived()) {
                return;
            }

            $outcome->restore();
            $this->auditTrail->record(new AuditedAction('outcome.restored', 'outcome', $outcome->id()));
        });
    }
}
