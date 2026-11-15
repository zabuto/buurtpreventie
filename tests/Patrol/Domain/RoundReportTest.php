<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\NotAWalkerOfThisRound;
use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\RoundNotWalked;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Reporting back after a round. Every round starts on 20 November 2026 at 22:00 in Europe/Amsterdam.
 */
final class RoundReportTest extends TestCase
{
    private const string AFTER_THE_ROUND = '2026-11-21T08:00:00+00:00';

    private Uuid $creator;

    #[Override]
    protected function setUp(): void
    {
        $this->creator = Uuid::v7();
    }

    /**
     * @return iterable<string, array{OutcomeKind, string|null}>
     */
    public static function outcomeKinds(): iterable
    {
        yield 'nothing to report' => [OutcomeKind::NothingToReport, null];
        yield 'remarks' => [OutcomeKind::Remarks, 'Lantaarnpaal kapot bij de school'];
        yield 'incident' => [OutcomeKind::Incident, 'Lantaarnpaal kapot bij de school'];
    }

    #[DataProvider('outcomeKinds')]
    #[Test]
    public function it_drops_the_note_when_there_is_nothing_to_report(OutcomeKind $kind, ?string $expectedNote): void
    {
        $round = RoundFixture::withWalkers($this->creator, 2);

        $report = $round->report($this->creator, Outcome::create('Uitkomst', $kind), ' Lantaarnpaal kapot bij de school ', $this->afterTheRound());

        $this->assertSame($expectedNote, $report->note());
    }

    #[Test]
    public function it_stores_no_note_when_it_is_blank(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 2);

        $report = $round->report($this->creator, Outcome::create('Opmerkingen', OutcomeKind::Remarks), "  \n ", $this->afterTheRound());

        $this->assertNull($report->note());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function roundsThatWereNotWalked(): iterable
    {
        yield 'past without a buddy' => [1, self::AFTER_THE_ROUND];
        yield 'staffed, before the start' => [2, RoundFixture::BEFORE_START];
    }

    #[DataProvider('roundsThatWereNotWalked')]
    #[Test]
    public function it_refuses_a_report_for_a_round_that_was_not_walked(int $walkers, string $now): void
    {
        $round = RoundFixture::withWalkers($this->creator, $walkers);

        $this->expectException(RoundNotWalked::class);

        $round->report($this->creator, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, new DateTimeImmutable($now));
    }

    #[Test]
    public function it_refuses_a_report_from_a_member_who_did_not_walk(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 2);

        $this->expectException(NotAWalkerOfThisRound::class);

        $round->report(Uuid::v7(), Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, $this->afterTheRound());
    }

    #[Test]
    public function it_replaces_the_report_of_the_same_walker(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 2);
        $incident = Outcome::create('Incident', OutcomeKind::Incident);
        $round->report($this->creator, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, $this->afterTheRound());

        $round->report($this->creator, $incident, 'Inbraak gezien', new DateTimeImmutable('2026-11-21T09:00:00+00:00'));

        $this->assertCount(1, $round->reports());
        $report = $round->reportOf($this->creator);
        $this->assertSame($incident, $report?->outcome());
        $this->assertSame('Inbraak gezien', $report->note());
        $this->assertEquals(new DateTimeImmutable('2026-11-21T09:00:00+00:00'), $report->reportedAt());
    }

    #[Test]
    public function it_keeps_one_report_per_walker(): void
    {
        $round = RoundFixture::withWalkers($this->creator, 1);
        $buddy = Uuid::v7();
        $round->signUp($buddy, new DateTimeImmutable(RoundFixture::BEFORE_START));
        $round->report($this->creator, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, $this->afterTheRound());

        $round->report($buddy, Outcome::create('Opmerkingen', OutcomeKind::Remarks), 'Hek open', $this->afterTheRound());

        $this->assertCount(2, $round->reports());
    }

    private function afterTheRound(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::AFTER_THE_ROUND);
    }
}
