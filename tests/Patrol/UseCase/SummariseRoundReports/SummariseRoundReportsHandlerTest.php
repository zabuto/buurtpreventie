<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\SummariseRoundReports;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\SummariseRoundReports\SummariseRoundReports;
use App\Patrol\UseCase\SummariseRoundReports\SummariseRoundReportsHandler;
use App\Shared\Domain\AccessDenied;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SummariseRoundReportsHandlerTest extends TestCase
{
    private InMemoryRoundRepository $rounds;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
    }

    #[Test]
    public function it_counts_the_reports_per_outcome_and_the_incidents_per_local_month_without_notes(): void
    {
        $quiet = Outcome::create('Niets bijzonders', OutcomeKind::NothingToReport);
        $burglary = Outcome::create('Inbraak', OutcomeKind::Incident);
        $this->reported('2026-10-05T20:00:00+00:00', $quiet);
        $this->reported('2026-10-12T20:00:00+00:00', $quiet);
        $this->reported('2026-10-31T23:30:00+00:00', $burglary, 'Achterdeur open bij nummer 4.');

        $output = $this->handler()(new SummariseRoundReports('2026-10-01T00:00:00+02:00', '2026-12-01T00:00:00+01:00'));

        $this->assertSame(3, $output->reports);
        $this->assertSame(1, $output->incidents);
        $this->assertSame(
            [
                ['outcomeId' => $quiet->id()->toRfc4122(), 'outcome' => 'Niets bijzonders', 'outcomeKind' => 'nothing_to_report', 'count' => 2],
                ['outcomeId' => $burglary->id()->toRfc4122(), 'outcome' => 'Inbraak', 'outcomeKind' => 'incident', 'count' => 1],
            ],
            $output->outcomes,
        );
        $this->assertSame([['month' => '2026-10', 'incidents' => 0], ['month' => '2026-11', 'incidents' => 1]], $output->incidentsByMonth);
        $this->assertStringNotContainsString('Achterdeur', (string) \json_encode($output));
    }

    #[Test]
    public function it_summarises_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            new SummariseRoundReportsHandler($this->rounds, $authorization)(new SummariseRoundReports(
                '2026-10-01T00:00:00+02:00',
                '2026-11-01T00:00:00+01:00',
            ));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['report.list', null]], $authorization->asked);
    }

    private function reported(string $startsAt, Outcome $outcome, ?string $note = null): void
    {
        $first = Uuid::v7();
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            $first,
            new StaffingRule(minimumWalkers: 1, maximumWalkers: 3),
            new DateTimeImmutable('2026-09-01T12:00:00+00:00'),
        );
        $round->submitReport($first, $outcome, $note, new DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $this->rounds->add($round);
    }

    private function handler(): SummariseRoundReportsHandler
    {
        return new SummariseRoundReportsHandler($this->rounds, FakeAuthorization::granting());
    }
}
