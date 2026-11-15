<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeArchived;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\RoundAlreadyReported;
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

    private Uuid $firstWalker;

    #[Override]
    protected function setUp(): void
    {
        $this->firstWalker = Uuid::v7();
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
        $round = RoundFixture::withWalkers($this->firstWalker, 2);

        $report = $round->submitReport($this->firstWalker, Outcome::create('Uitkomst', $kind), ' Lantaarnpaal kapot bij de school ', $this->afterTheRound());

        $this->assertSame($expectedNote, $report->note());
    }

    #[Test]
    public function it_stores_no_note_when_it_is_blank(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 2);

        $report = $round->submitReport($this->firstWalker, Outcome::create('Opmerkingen', OutcomeKind::Remarks), "  \n ", $this->afterTheRound());

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
        $round = RoundFixture::withWalkers($this->firstWalker, $walkers);

        $this->expectException(RoundNotWalked::class);

        $round->submitReport($this->firstWalker, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, new DateTimeImmutable($now));
    }

    #[Test]
    public function it_keeps_the_author_of_the_report(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 2);
        $coordinator = Uuid::v7();

        $report = $round->submitReport($coordinator, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, $this->afterTheRound());

        $this->assertEquals($coordinator, $report->authorId());
        $this->assertSame($report, $round->report());
    }

    #[Test]
    public function it_refuses_a_second_report(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 2);
        $round->submitReport($this->firstWalker, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, $this->afterTheRound());

        $this->expectException(RoundAlreadyReported::class);

        $round->submitReport(Uuid::v7(), Outcome::create('Incident', OutcomeKind::Incident), 'Inbraak gezien', $this->afterTheRound());
    }

    #[Test]
    public function it_takes_a_new_report_once_the_old_one_is_removed(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 2);
        $round->submitReport($this->firstWalker, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, $this->afterTheRound());
        $round->removeReport();
        $incident = Outcome::create('Incident', OutcomeKind::Incident);

        $report = $round->submitReport($this->firstWalker, $incident, 'Inbraak gezien', $this->afterTheRound());

        $this->assertSame([$report, $incident], [$round->report(), $report->outcome()]);
    }

    #[Test]
    public function it_refuses_a_report_with_an_archived_outcome(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 2);
        $outcome = Outcome::create('Niets te melden', OutcomeKind::NothingToReport);
        $outcome->archive();

        $this->expectException(OutcomeArchived::class);

        $round->submitReport($this->firstWalker, $outcome, null, $this->afterTheRound());
    }

    private function afterTheRound(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::AFTER_THE_ROUND);
    }
}
