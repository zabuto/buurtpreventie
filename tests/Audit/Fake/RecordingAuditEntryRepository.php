<?php

declare(strict_types=1);

namespace App\Tests\Audit\Fake;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Audit\Domain\AuditFilter;
use Override;
use Symfony\Component\Uid\Uuid;

/**
 * Holds the entries a test arranges and records whose name was erased; the bulk update itself has its own database
 * test.
 */
final class RecordingAuditEntryRepository implements AuditEntryRepository
{
    /** @var list<Uuid> */
    public array $erasedActorNames = [];

    /** @var list<AuditEntry> */
    private array $entries = [];

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
    public function eraseActorName(Uuid $memberId): void
    {
        $this->erasedActorNames[] = $memberId;
    }
}
