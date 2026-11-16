<?php

declare(strict_types=1);

namespace App\Tests\Audit\Fake;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Audit\Domain\AuditFilter;
use App\Audit\Domain\ChainLink;
use LogicException;
use Override;
use Symfony\Component\Uid\Uuid;

/**
 * Holds the entries a test arranges, the head and anchor of the chain, and records whose name was erased; the bulk
 * updates and the lock on the head have their own database test.
 *
 * @mago-expect lint:cyclomatic-complexity - one fake for the whole interface, so a test arranges entries and chain in one place
 */
final class RecordingAuditEntryRepository implements AuditEntryRepository
{
    /** @var list<Uuid> */
    public array $erasedActorNames = [];

    /** @var list<AuditEntry> */
    private array $entries = [];

    private ChainLink $head;
    private ChainLink $anchor;

    public function __construct()
    {
        $this->head = ChainLink::start();
        $this->anchor = ChainLink::start();
    }

    /**
     * Sets both fixed points of the chain, as after pruning or for entries from before the chain.
     */
    public function startChainAt(ChainLink $link): void
    {
        $this->head = $link;
        $this->anchor = $link;
    }

    /**
     * Replaces a stored entry with another one, as someone with access to the database could; null removes it.
     */
    public function replace(AuditEntry $entry, ?AuditEntry $replacement): void
    {
        $index = \array_search($entry, $this->entries, strict: true);
        \assert(\is_int($index), 'Only a stored entry is replaced.');
        \array_splice($this->entries, $index, 1, null === $replacement ? [] : [$replacement]);
    }

    /**
     * @return list<AuditEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function add(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    #[Override]
    public function byActor(Uuid $memberId, int $offset, int $limit): array
    {
        $entries = \array_values(\array_filter(
            $this->entries,
            static fn(AuditEntry $entry): bool => true === $entry->actorId()?->equals($memberId),
        ));
        \usort(
            $entries,
            static fn(AuditEntry $a, AuditEntry $b): int => [$b->occurredAt(), $b->id()->toRfc4122()] <=> [$a->occurredAt(), $a->id()->toRfc4122()],
        );

        return \array_slice($entries, $offset, $limit);
    }

    #[Override]
    public function bySubject(string $subjectType, Uuid $subjectId): array
    {
        $entries = \array_values(\array_filter(
            $this->entries,
            static fn(AuditEntry $entry): bool => $entry->subjectType() === $subjectType && $entry->subjectId()->equals($subjectId),
        ));
        \usort(
            $entries,
            static fn(AuditEntry $a, AuditEntry $b): int => [$a->occurredAt(), $a->id()->toRfc4122()] <=> [$b->occurredAt(), $b->id()->toRfc4122()],
        );

        return $entries;
    }

    #[Override]
    public function matching(AuditFilter $filter, int $offset, int $limit): array
    {
        $entries = \array_values(\array_filter($this->entries, $filter->matches(...)));
        \usort(
            $entries,
            static fn(AuditEntry $a, AuditEntry $b): int => [$b->occurredAt(), $b->id()->toRfc4122()] <=> [$a->occurredAt(), $a->id()->toRfc4122()],
        );

        return \array_slice($entries, $offset, $limit);
    }

    #[Override]
    public function countMatching(AuditFilter $filter): int
    {
        return \count(\array_filter($this->entries, $filter->matches(...)));
    }

    #[Override]
    public function eraseActorName(Uuid $memberId): void
    {
        $this->erasedActorNames[] = $memberId;
    }

    #[Override]
    public function eraseChangesAbout(Uuid $memberId): void
    {
        $about = \array_filter(
            $this->entries,
            static fn(AuditEntry $entry): bool => 'member' === $entry->subjectType() && $entry->subjectId()->equals($memberId),
        );
        \array_map(static fn(AuditEntry $entry) => $entry->eraseChanges(), $about);
    }

    #[Override]
    public function lockHead(): ChainLink
    {
        return $this->head;
    }

    #[Override]
    public function append(AuditEntry $entry): void
    {
        $link = $entry->chainLink();
        if (null === $link) {
            throw new LogicException('Only a linked entry joins the chain.');
        }

        $this->entries[] = $entry;
        $this->head = $link;
    }

    #[Override]
    public function head(): ChainLink
    {
        return $this->head;
    }

    #[Override]
    public function anchor(): ChainLink
    {
        return $this->anchor;
    }

    #[Override]
    public function chainAfter(int $position, int $limit): array
    {
        $entries = \array_values(\array_filter(
            $this->entries,
            static fn(AuditEntry $entry): bool => $entry->chainPosition() > $position,
        ));
        \usort($entries, static fn(AuditEntry $a, AuditEntry $b): int => $a->chainPosition() <=> $b->chainPosition());

        return \array_slice($entries, 0, $limit);
    }

    #[Override]
    public function countUnchainedThrough(int $position): int
    {
        return \count(\array_filter(
            $this->entries,
            static fn(AuditEntry $entry): bool => ($entry->chainPosition() ?? \PHP_INT_MAX) <= $position && null === $entry->chainLink(),
        ));
    }

    #[Override]
    public function pruneThrough(int $position): void
    {
        if ($position > $this->anchor->position) {
            $next = $this->chainAfter($position - 1, 1)[0] ?? null;
            $link = $next?->chainLink();
            if (null === $link || $link->position !== $position) {
                throw new LogicException(\sprintf('No linked audit entry holds position %d.', $position));
            }

            $this->anchor = $link;
        }

        $this->entries = \array_values(\array_filter(
            $this->entries,
            static fn(AuditEntry $entry): bool => ($entry->chainPosition() ?? \PHP_INT_MAX) > $position,
        ));
    }
}
