<?php

declare(strict_types=1);

namespace App\Tests\Audit\Domain;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\ChainBreakReason;
use App\Audit\Domain\ChainLink;
use App\Audit\Domain\ChainReport;
use App\Audit\Domain\ChainVerifier;
use App\Audit\Infrastructure\HmacChainHasher;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Audit\Fake\TamperedEntry;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ChainVerifierTest extends TestCase
{
    private HmacChainHasher $hasher;

    #[\Override]
    protected function setUp(): void
    {
        $this->hasher = new HmacChainHasher('audit-key');
    }

    #[Test]
    public function it_reports_an_intact_chain_through_its_last_entry(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);

        $report = $this->verify(ChainLink::start(), $entries, $head);

        $this->assertSame(
            [null, 3, '2026-11-15T12:03:00+00:00', 3],
            [$report->break, $report->intactThrough, $report->intactThroughAt?->format(\DATE_ATOM), $report->checked],
        );
    }

    #[Test]
    public function it_reports_an_empty_chain_as_intact_through_the_anchor(): void
    {
        $anchor = new ChainLink(12, \str_repeat('a', 32));

        $report = $this->verify($anchor, [], $anchor);

        $this->assertSame([null, 12, null, 0], [$report->break, $report->intactThrough, $report->intactThroughAt, $report->checked]);
    }

    /**
     * After pruning, the chain is checked from the hash of the last pruned entry.
     */
    #[Test]
    public function it_checks_the_chain_from_the_anchor(): void
    {
        $anchor = new ChainLink(40, \str_repeat('a', 32));
        [$entries, $head] = $this->chain($anchor, 2);

        $report = $this->verify($anchor, $entries, $head);

        $this->assertSame([null, 42], [$report->break, $report->intactThrough]);
    }

    /**
     * @return iterable<string, array{string, mixed, ChainBreakReason}>
     */
    public static function tamperings(): iterable
    {
        yield 'the action' => ['action', 'round.deleted', ChainBreakReason::ChangedContent];
        yield 'the time' => ['occurredAt', new DateTimeImmutable('2026-11-15T12:30:00+00:00'), ChainBreakReason::ChangedContent];
        yield 'the actor' => ['actorId', Uuid::fromString('0196b0a4-0000-7000-8000-000000000001'), ChainBreakReason::ChangedContent];
        yield 'the subject' => ['subjectId', Uuid::fromString('0196b0a4-0000-7000-8000-000000000002'), ChainBreakReason::ChangedContent];
        yield 'the channel' => ['channel', Channel::Mcp, ChainBreakReason::ChangedContent];
        yield 'a changed field' => ['changes', '{"startsAt":{"old":"22:00","new":"23:30"}}', ChainBreakReason::ChangedContent];
        yield 'the hash of the changed fields' => ['changesHash', \str_repeat('c', 32), ChainBreakReason::ChangedContent];
        yield 'the hash of the entry' => ['chainHash', \str_repeat('h', 32), ChainBreakReason::InsertedOrRelinked];
    }

    #[Test]
    #[DataProvider('tamperings')]
    public function it_points_at_an_entry_whose_stored_value_changed(string $property, mixed $value, ChainBreakReason $reason): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);
        $tampered = [$entries[0], TamperedEntry::of($entries[1], [$property => $value]), $entries[2]];

        $report = $this->verify(ChainLink::start(), $tampered, $head);

        $this->assertSame([$reason, 2, 1], [$report->break?->reason, $report->break?->position, $report->intactThrough]);
    }

    /**
     * Without an entry after it, the head confirms that the stored hash of the last entry belongs to the chain.
     */
    #[Test]
    public function it_points_at_a_changed_last_entry(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);
        $tampered = [$entries[0], $entries[1], TamperedEntry::of($entries[2], ['action' => 'round.deleted'])];

        $report = $this->verify(ChainLink::start(), $tampered, $head);

        $this->assertSame([ChainBreakReason::ChangedContent, 3], [$report->break?->reason, $report->break?->position]);
    }

    #[Test]
    public function it_points_at_the_position_of_a_removed_entry(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);
        unset($entries[1]);

        $report = $this->verify(ChainLink::start(), \array_values($entries), $head);

        $this->assertSame([ChainBreakReason::MissingEntry, 2, 1], [$report->break?->reason, $report->break?->position, $report->intactThrough]);
    }

    /**
     * Whoever removes an entry and renumbers the ones after it to close the gap cannot give them a fitting hash.
     */
    #[Test]
    public function it_points_at_the_first_entry_renumbered_after_a_removal(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);
        $renumbered = [$entries[0], TamperedEntry::of($entries[2], ['chainPosition' => 2])];

        $report = $this->verify(ChainLink::start(), $renumbered, $head);

        $this->assertSame([ChainBreakReason::InsertedOrRelinked, 2], [$report->break?->reason, $report->break?->position]);
    }

    #[Test]
    public function it_points_at_an_entry_inserted_between_two_others(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 2);
        $forged = TamperedEntry::of($entries[1], ['id' => Uuid::v7(), 'chainHash' => \str_repeat('f', 32)]);
        $shifted = [$entries[0], $forged, TamperedEntry::of($entries[1], ['chainPosition' => 3])];

        $report = $this->verify(ChainLink::start(), $shifted, new ChainLink(3, $head->hash));

        $this->assertSame([ChainBreakReason::InsertedOrRelinked, 2], [$report->break?->reason, $report->break?->position]);
    }

    #[Test]
    public function it_points_at_the_first_of_two_entries_that_swapped_places(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);
        $swapped = [
            $entries[0],
            TamperedEntry::of($entries[2], ['chainPosition' => 2]),
            TamperedEntry::of($entries[1], ['chainPosition' => 3]),
        ];

        $report = $this->verify(ChainLink::start(), $swapped, $head);

        $this->assertSame([ChainBreakReason::InsertedOrRelinked, 2], [$report->break?->reason, $report->break?->position]);
    }

    #[Test]
    public function it_reports_a_head_that_points_past_the_last_entry(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);
        \array_pop($entries);

        $report = $this->verify(ChainLink::start(), $entries, $head);

        $this->assertSame([ChainBreakReason::HeadMismatch, 3, 2], [$report->break?->reason, $report->break?->position, $report->intactThrough]);
    }

    /**
     * Entries with a valid hash after the head show that someone set the head back to an earlier entry.
     */
    #[Test]
    public function it_reports_a_head_set_back_to_an_earlier_entry(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 3);
        $earlier = $entries[1]->chainLink();
        $this->assertNotNull($earlier);

        $report = $this->verify(ChainLink::start(), $entries, $earlier);

        $this->assertSame([ChainBreakReason::HeadMismatch, 2], [$report->break?->reason, $report->break?->position]);
    }

    #[Test]
    public function it_counts_an_entry_with_erased_changes_as_intact(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 2, ['displayName' => ['old' => null, 'new' => 'Anna H.']]);
        $entries[0]->eraseChanges();

        $report = $this->verify(ChainLink::start(), $entries, $head);

        $this->assertSame([null, 2, 1], [$report->break, $report->intactThrough, $report->erased]);
    }

    #[Test]
    public function it_reports_the_head_it_checked_against(): void
    {
        [$entries, $head] = $this->chain(ChainLink::start(), 2);

        $report = $this->verify(ChainLink::start(), $entries, $head);

        $this->assertTrue($report->head->equals($head));
    }

    /**
     * @param array<string, array{old: scalar|null, new: scalar|null}> $changes
     *
     * @return array{list<AuditEntry>, ChainLink} the entries after $anchor, each linked to the one before, and the head
     */
    private function chain(ChainLink $anchor, int $length, array $changes = ['startsAt' => ['old' => '22:00', 'new' => '23:00']]): array
    {
        $entries = [];
        $link = $anchor;
        for ($minute = 1; $minute <= $length; ++$minute) {
            $entry = AuditEntry::of(
                new AuditedAction('round.changed', 'round', Uuid::v7(), $changes),
                Uuid::v7(),
                'Anna H.',
                Channel::Web,
                new DateTimeImmutable(\sprintf('2026-11-15T12:%02d:00+00:00', $minute)),
            );
            $link = $entry->link($link, $this->hasher);
            $entries[] = $entry;
        }

        return [$entries, $link];
    }

    /**
     * @param list<AuditEntry> $entries
     */
    private function verify(ChainLink $anchor, array $entries, ChainLink $head): ChainReport
    {
        $verifier = new ChainVerifier($this->hasher, $anchor);
        foreach ($entries as $entry) {
            $verifier->add($entry);
        }

        return $verifier->finish($head);
    }
}
