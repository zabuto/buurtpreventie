<?php

declare(strict_types=1);

namespace App\Audit\UseCase\EraseActorName;

use App\Audit\Domain\AuditEntryRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * Shows an erased member in the log as "removed member": the name goes, and so do the changed fields of every entry
 * about the member, such as the display name the member was added with; the actions stay, and so does the chain.
 */
#[WriteAction(authorization: 'audit.erase_actor_name', audit: 'audit.actor_name_erased')]
final readonly class EraseActorNameHandler
{
    public function __construct(
        private AuditEntryRepository $entries,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EraseActorName $input): void
    {
        $memberId = Uuid::fromString($input->memberId);
        $this->authorization->denyUnlessGranted('audit.erase_actor_name', $memberId);

        $this->transaction->run(function () use ($memberId): void {
            $this->entries->eraseActorName($memberId);
            $this->entries->eraseChangesAbout($memberId);
            $this->auditTrail->record(new AuditedAction('audit.actor_name_erased', 'member', $memberId));
        });
    }
}
