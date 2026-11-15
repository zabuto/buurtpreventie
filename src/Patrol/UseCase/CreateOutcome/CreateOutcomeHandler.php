<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateOutcome;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\OutcomeNameTaken;
use App\Patrol\Domain\OutcomeRepository;
use App\Patrol\Domain\UniqueCatalogueName;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;

#[WriteAction(authorization: 'catalogue.manage', audit: 'outcome.created')]
final readonly class CreateOutcomeHandler
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
     */
    public function __invoke(CreateOutcome $input): CreateOutcomeOutput
    {
        $this->authorization->denyUnlessGranted('catalogue.manage');

        return $this->transaction->run(function () use ($input): CreateOutcomeOutput {
            $this->uniqueName->ensureOutcomeNameAvailable($input->name);

            $outcome = Outcome::create($input->name, OutcomeKind::from($input->kind));
            $this->outcomes->add($outcome);
            $this->auditTrail->record(new AuditedAction('outcome.created', 'outcome', $outcome->id(), [
                'name' => ['old' => null, 'new' => $outcome->name()],
                'kind' => ['old' => null, 'new' => $outcome->kind()->value],
            ]));

            return new CreateOutcomeOutput($outcome->id()->toRfc4122());
        });
    }
}
