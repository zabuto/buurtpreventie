<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Audit\Domain\ChainHasher;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\MemberNames;
use App\Shared\Domain\Transaction;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Links each entry to the chain as the last step of the running transaction: it locks the head, takes the next
 * position and stores the entry with the change it describes.
 *
 * Last, because a lock on the head taken any earlier would be held while the transaction waits for another lock,
 * such as that of a round, and two such transactions could deadlock. A rollback drops the step, so a failed
 * transaction takes no position.
 */
final readonly class ChainedAuditTrail implements AuditTrail
{
    public function __construct(
        private AuditEntryRepository $entries,
        private Transaction $transaction,
        private ChainHasher $hasher,
        private ClockInterface $clock,
        private ExecutionContext $executionContext,
        private MemberNames $memberNames,
    ) {
    }

    #[Override]
    public function record(AuditedAction $action): void
    {
        $actorId = $this->executionContext->actor()?->memberId;
        $entry = AuditEntry::of(
            $action,
            $actorId,
            null === $actorId ? null : $this->memberNames->displayNameOf($actorId),
            $this->executionContext->channel(),
            $this->clock->now(),
        );

        $this->transaction->beforeCommit(function () use ($entry): void {
            $entry->link($this->entries->lockHead(), $this->hasher);
            $this->entries->append($entry);
        });
    }
}
