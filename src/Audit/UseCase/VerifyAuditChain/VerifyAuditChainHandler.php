<?php

declare(strict_types=1);

namespace App\Audit\UseCase\VerifyAuditChain;

use App\Audit\Domain\AuditEntryRepository;
use App\Audit\Domain\ChainHasher;
use App\Audit\Domain\ChainVerifier;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use App\Shared\Domain\Transaction;

/**
 * Checks the audit chain from the anchor to the head and reports how far it holds, or the first entry that does not.
 * Everything is read in one transaction, so head, anchor and entries come from one snapshot: an entry written or
 * pruned meanwhile is not taken for a break.
 */
#[ReadAction]
final readonly class VerifyAuditChainHandler
{
    private const int BATCH = 500;

    public function __construct(
        private AuditEntryRepository $entries,
        private ChainHasher $hasher,
        private Authorization $authorization,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(VerifyAuditChain $input): VerifyAuditChainOutput
    {
        $this->authorization->denyUnlessGranted('audit.verify');

        return $this->transaction->run(function (): VerifyAuditChainOutput {
            $head = $this->entries->head();
            $anchor = $this->entries->anchor();
            $verifier = new ChainVerifier($this->hasher, $anchor);
            $position = $anchor->position;
            do {
                $batch = $this->entries->chainAfter($position, self::BATCH);
                foreach ($batch as $entry) {
                    $verifier->add($entry);
                    $position = (int) $entry->chainPosition();
                }
            } while (self::BATCH === \count($batch) && !$verifier->isBroken());

            $report = $verifier->finish($head);
            $intactThroughAt = null === $report->intactThroughAt ? null : LocalTime::of($report->intactThroughAt)->format(\DATE_ATOM);

            return new VerifyAuditChainOutput(
                intact: null === $report->break,
                intactThrough: $report->intactThrough,
                intactThroughAt: $intactThroughAt,
                checked: $report->checked,
                erased: $report->erased,
                beforeChain: $this->entries->countUnchainedThrough($anchor->position),
                lastPosition: $head->position,
                breakReason: $report->break?->reason->value,
                breakPosition: $report->break?->position,
            );
        });
    }
}
