<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\RoundStatistics;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\RoundStatistics\RoundStatistics;
use App\Patrol\UseCase\RoundStatistics\RoundStatisticsHandler;
use App\Shared\Domain\AccessDenied;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class RoundStatisticsHandlerTest extends TestCase
{
    private const string NOW = '2026-11-16T10:00:00+00:00';
    private const string CREATED_AT = '2026-08-01T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
    }

    #[Test]
    public function it_counts_the_rounds_per_local_month_next_to_the_months_before(): void
    {
        $this->round('2026-09-10T20:00:00+00:00', walkers: 2);
        $this->round('2026-10-10T20:00:00+00:00', walkers: 2, incident: true);
        $this->round('2026-10-31T23:30:00+00:00', walkers: 1);
        $this->round('2026-11-20T20:00:00+00:00', walkers: 1);

        $output = $this->handler()(new RoundStatistics('2026-10-01T00:00:00+02:00', '2026-12-01T00:00:00+01:00', 'month'));

        $this->assertSame(
            [
                [
                    'group' => '2026-10',
                    'planned' => 1,
                    'walked' => 1,
                    'notWalked' => 0,
                    'cancelled' => 0,
                    'upcoming' => 0,
                    'incidents' => 1,
                    'walkedPercentage' => 100.0,
                    'notWalkedPercentage' => 0.0,
                ],
                [
                    'group' => '2026-11',
                    'planned' => 2,
                    'walked' => 0,
                    'notWalked' => 1,
                    'cancelled' => 0,
                    'upcoming' => 1,
                    'incidents' => 0,
                    'walkedPercentage' => 0.0,
                    'notWalkedPercentage' => 100.0,
                ],
            ],
            $output->groups,
        );
        $this->assertSame(3, $output->total['planned']);
        $this->assertSame(['2026-08-01T00:00:00+02:00', '2026-10-01T00:00:00+02:00', 1, 1], [
            $output->previous['from'],
            $output->previous['to'],
            $output->previous['planned'],
            $output->previous['walked'],
        ]);
    }

    #[Test]
    public function it_lists_every_weekday_also_without_rounds(): void
    {
        $this->round('2026-11-12T20:00:00+00:00', walkers: 2);

        $output = $this->handler()(new RoundStatistics('2026-11-09T00:00:00+01:00', '2026-11-16T00:00:00+01:00', 'weekday'));

        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], \array_column($output->groups, 'group'));
        $this->assertSame([0, 0, 0, 1, 0, 0, 0], \array_column($output->groups, 'planned'));
    }

    /**
     * A round at 22:00 just after the clocks went back is still an evening round (review focus 2).
     */
    #[Test]
    public function it_puts_a_round_in_its_local_daypart_across_the_clock_change(): void
    {
        $this->round('2026-10-25T21:00:00+00:00', walkers: 2);

        $output = $this->handler()(new RoundStatistics('2026-10-25T00:00:00+02:00', '2026-10-26T00:00:00+01:00', 'daypart'));

        $this->assertSame([0, 0, 1], \array_column($output->groups, 'planned'));
    }

    #[Test]
    public function it_refuses_a_period_of_more_than_366_days(): void
    {
        $this->expectException(InvalidPeriod::class);

        $this->handler()(new RoundStatistics('2026-01-01T00:00:00+01:00', '2027-01-03T00:00:00+01:00', 'month'));
    }

    #[Test]
    public function it_counts_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new RoundStatistics('2026-10-01T00:00:00+02:00', '2026-11-01T00:00:00+01:00', 'month'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['round.statistics', null]], $authorization->asked);
    }

    private function round(string $startsAt, int $walkers, bool $incident = false): Round
    {
        $first = Uuid::v7();
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            $first,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(self::CREATED_AT),
        );
        for ($walker = 1; $walker < $walkers; ++$walker) {
            $round->signUp(Uuid::v7(), new DateTimeImmutable(self::CREATED_AT));
        }

        if ($incident) {
            $round->submitReport($first, Outcome::create('Inbraak', OutcomeKind::Incident), null, new DateTimeImmutable(self::NOW));
        }

        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FakeAuthorization $authorization = null): RoundStatisticsHandler
    {
        return new RoundStatisticsHandler($this->rounds, $authorization ?? FakeAuthorization::granting(), new MockClock(self::NOW));
    }
}
