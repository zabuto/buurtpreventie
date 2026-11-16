<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use DateTimeImmutable;

/**
 * Walks the audit chain from the anchor, one entry at a time in the order of their position, and finds the first
 * place that does not hold.
 *
 * An entry whose hash does not fit is judged by what follows it: when the next entry, or the head, links to its
 * stored hash, that hash was part of the chain and a value of the entry changed afterwards; otherwise the entry was
 * never part of the chain at this place.
 */
final class ChainVerifier
{
    private ?AuditEntry $suspect = null;
    private ?ChainBreak $break = null;
    private ?DateTimeImmutable $intactThroughAt = null;
    private int $checked = 0;
    private int $erased = 0;

    /**
     * @param ChainLink $previous the anchor at first, where the check starts; then the last entry that holds
     */
    public function __construct(
        private readonly ChainHasher $hasher,
        private ChainLink $previous,
    ) {
    }

    /**
     * Whether a break is found; reading further entries changes nothing then.
     */
    public function isBroken(): bool
    {
        return null !== $this->break;
    }

    public function add(AuditEntry $entry): void
    {
        if (null !== $this->break) {
            return;
        }

        if (null !== $this->suspect) {
            $this->judgeSuspect(fn(ChainLink $link): bool => $entry->linksTo($link, $this->hasher));

            return;
        }

        $this->check($entry);
    }

    public function finish(ChainLink $head): ChainReport
    {
        $this->checkHead($head);

        return new ChainReport($this->break, $this->previous->position, $this->intactThroughAt, $this->checked, $this->erased, $head);
    }

    private function checkHead(ChainLink $head): void
    {
        if (null !== $this->suspect) {
            $this->judgeSuspect($head->equals(...));

            return;
        }

        $this->break ??= $head->equals($this->previous) ? null : new ChainBreak(ChainBreakReason::HeadMismatch, $head->position);
    }

    private function check(AuditEntry $entry): void
    {
        $position = $this->previous->position + 1;
        if ($entry->chainPosition() !== $position) {
            $this->break = new ChainBreak(ChainBreakReason::MissingEntry, $position);

            return;
        }

        $link = $entry->linksTo($this->previous, $this->hasher) ? $entry->chainLink() : null;
        if (null === $link) {
            $this->suspect = $entry;

            return;
        }

        if (!$entry->hasIntactChanges($this->hasher)) {
            $this->break = new ChainBreak(ChainBreakReason::ChangedContent, $position);

            return;
        }

        $this->previous = $link;
        $this->intactThroughAt = $entry->occurredAt();
        ++$this->checked;
        $this->erased += $entry->hasErasedChanges() ? 1 : 0;
    }

    /**
     * @param callable(ChainLink): bool $confirms whether the next entry or the head links to a stored hash
     */
    private function judgeSuspect(callable $confirms): void
    {
        $link = $this->suspect?->chainLink();
        $this->judge(null !== $link && $confirms($link));
    }

    /**
     * @param bool $confirmed whether the next entry or the head links to the stored hash of the suspect
     */
    private function judge(bool $confirmed): void
    {
        $this->break = new ChainBreak(
            $confirmed ? ChainBreakReason::ChangedContent : ChainBreakReason::InsertedOrRelinked,
            $this->previous->position + 1,
        );
        $this->suspect = null;
    }
}
