<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeOutcome;

use App\Patrol\Domain\OutcomeNameTaken;
use App\Patrol\Domain\OutcomeRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'catalogue.manage', audit: 'outcome.changed')]
final readonly class ChangeOutcomeHandler
{
    public function __construct(
        private OutcomeRepository $outcomes,
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

            // Another spelling of its own name is no clash.
            $existing = $this->outcomes->findByNameKey(LookupKey::fromName($input->name));
            if (null !== $existing && !$existing->id()->equals($outcome->id())) {
                throw OutcomeNameTaken::by($existing);
            }

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
