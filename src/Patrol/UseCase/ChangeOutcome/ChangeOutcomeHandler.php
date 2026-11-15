<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeOutcome;

use App\Patrol\Domain\Catalogue\OutcomeNameTaken;
use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Patrol\Domain\Catalogue\UniqueCatalogueName;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'catalogue.manage', audit: 'outcome.changed')]
final readonly class ChangeOutcomeHandler
{
    public function __construct(
        private OutcomeRepository $outcomes,
        private UniqueCatalogueName $uniqueName,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws OutcomeNameTaken
     * @throws NotFound
     */
    public function __invoke(ChangeOutcome $input): void
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');

        $this->transaction->run(function () use ($input): void {
            $outcome = $this->outcomes->get(Uuid::fromString($input->outcomeId));
            $this->uniqueName->ensureOutcomeNameAvailable($input->name, $outcome->id());

            $oldName = $outcome->name();
            $outcome->rename($input->name);
            $this->outcomes->update($outcome);
            if ($oldName === $outcome->name()) {
                return;
            }

            $this->auditTrail->record(new AuditedAction('outcome.changed', 'outcome', $outcome->id(), [
                'name' => ['old' => $oldName, 'new' => $outcome->name()],
            ]));
        });
    }
}
