<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListMyRounds;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\ListMyRounds\ListMyRounds;
use App\Patrol\UseCase\ListMyRounds\ListMyRoundsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FixedExecutionContext;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ListMyRoundsHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string CREATED_AT = '2026-11-01T12:00:00+00:00';
    private const string FROM = '2026-11-01T00:00:00+01:00';
    private const string TO = '2026-12-01T00:00:00+01:00';

    private InMemoryRoundRepository $rounds;
    private Uuid $jan;
    private Uuid $piet;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->jan = Uuid::v7();
        $this->piet = Uuid::v7();
    }

    #[Test]
    public function it_lists_every_round_of_the_member_in_the_period_by_start_with_their_staffing_in_local_time(): void
    {
        $later = $this->round('2026-11-16T21:00:00+00:00', $this->jan, MeetingPoint::create('Speeltuin'));
        $earlier = $this->round('2026-11-14T20:00:00+00:00', $this->piet);
        $earlier->signUp($this->jan, new DateTimeImmutable(self::CREATED_AT));

        $output = $this->handler()(new ListMyRounds(view: 'all', from: self::FROM, to: self::TO));

        $this->assertSame(
            [
                [
                    'id' => $earlier->id()->toRfc4122(),
                    'startsAt' => '2026-11-14T21:00:00+01:00',
                    'daypart' => 'evening',
                    'status' => 'walked',
                    'walkerCount' => 2,
                    'minimumWalkers' => 2,
                    'maximumWalkers' => 3,
                    'openSpots' => 0,
                    'meetingPoint' => null,
                    'reported' => false,
                ],
                [
                    'id' => $later->id()->toRfc4122(),
                    'startsAt' => '2026-11-16T22:00:00+01:00',
                    'daypart' => 'evening',
                    'status' => 'seeking_buddy',
                    'walkerCount' => 1,
                    'minimumWalkers' => 2,
                    'maximumWalkers' => 3,
                    'openSpots' => 2,
                    'meetingPoint' => 'Speeltuin',
                    'reported' => false,
                ],
            ],
            $output->rounds,
        );
    }

    #[Test]
    public function it_leaves_out_the_rounds_of_other_members(): void
    {
        $this->round('2026-11-16T21:00:00+00:00', $this->piet);

        $output = $this->handler()(new ListMyRounds(view: 'all', from: self::FROM, to: self::TO));

        $this->assertSame([], $output->rounds);
    }

    #[Test]
    public function it_lists_the_rounds_that_have_not_started_earliest_first_by_default(): void
    {
        $later = $this->round('2026-11-20T20:00:00+00:00', $this->jan);
        $sooner = $this->round('2026-11-16T20:00:00+00:00', $this->jan);
        $this->round('2026-11-14T20:00:00+00:00', $this->jan);

        $output = $this->handler()(new ListMyRounds());

        $this->assertSame(self::ids($sooner, $later), \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_lists_the_walked_rounds_without_a_report_latest_first(): void
    {
        $earlier = $this->walked('2026-11-10T20:00:00+00:00');
        $later = $this->walked('2026-11-14T20:00:00+00:00');
        $this->reported($this->walked('2026-11-12T20:00:00+00:00'));
        $this->round('2026-11-13T20:00:00+00:00', $this->jan);
        $this->round('2026-11-16T20:00:00+00:00', $this->jan);

        $output = $this->handler()(new ListMyRounds(view: 'to_report'));

        $this->assertSame(self::ids($later, $earlier), \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_lists_every_round_in_the_period_latest_first_when_asked(): void
    {
        $earlier = $this->walked('2026-11-10T20:00:00+00:00');
        $later = $this->round('2026-11-16T20:00:00+00:00', $this->jan);
        $this->round('2026-12-10T20:00:00+00:00', $this->jan);

        $output = $this->handler()(new ListMyRounds(view: 'all', from: self::FROM, to: self::TO, order: 'desc'));

        $this->assertSame(self::ids($later, $earlier), \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_narrows_the_rounds_still_to_walk_to_a_period(): void
    {
        $inside = $this->round('2026-11-16T20:00:00+00:00', $this->jan);
        $this->round('2026-12-10T20:00:00+00:00', $this->jan);

        $output = $this->handler()(new ListMyRounds(from: self::FROM, to: self::TO));

        $this->assertSame(self::ids($inside), \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_gives_no_more_rounds_than_the_limit(): void
    {
        $first = $this->round('2026-11-16T20:00:00+00:00', $this->jan);
        $this->round('2026-11-17T20:00:00+00:00', $this->jan);

        $output = $this->handler()(new ListMyRounds(limit: 1));

        $this->assertSame(self::ids($first), \array_column($output->rounds, 'id'));
    }

    /**
     * Every round of a member is the whole history, so that view asks for a period.
     *
     * @return iterable<string, array{ListMyRounds}>
     */
    public static function invalidPeriods(): iterable
    {
        yield 'every round without a period' => [new ListMyRounds(view: 'all')];
        yield 'a start without an end' => [new ListMyRounds(from: self::FROM)];
        yield 'an end without a start' => [new ListMyRounds(view: 'to_report', to: self::TO)];
        yield 'more than 93 days' => [new ListMyRounds(view: 'all', from: '2026-01-01T00:00:00+01:00', to: self::TO)];
    }

    #[DataProvider('invalidPeriods')]
    #[Test]
    public function it_refuses_a_period_that_is_missing_incomplete_or_too_long(ListMyRounds $input): void
    {
        $this->expectException(InvalidPeriod::class);

        $this->handler()($input);
    }

    #[Test]
    public function it_tells_which_walked_round_has_a_report(): void
    {
        $this->reported($this->walked('2026-11-14T20:00:00+00:00'));

        $output = $this->handler()(new ListMyRounds(view: 'all', from: self::FROM, to: self::TO));

        $this->assertTrue($output->rounds[0]['reported'] ?? null);
    }

    #[Test]
    public function it_refuses_when_nobody_acts(): void
    {
        $this->expectException(AccessDenied::class);

        $this->handler(withActor: false)(new ListMyRounds());
    }

    private function walked(string $startsAt): Round
    {
        $round = $this->round($startsAt, $this->jan);
        $round->signUp($this->piet, new DateTimeImmutable(self::CREATED_AT));

        return $round;
    }

    private function reported(Round $round): void
    {
        $round->submitReport($this->piet, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, new DateTimeImmutable(self::NOW));
    }

    /**
     * @return list<string>
     */
    private static function ids(Round ...$rounds): array
    {
        return \array_values(\array_map(static fn(Round $round): string => $round->id()->toRfc4122(), $rounds));
    }

    private function round(string $startsAt, Uuid $firstWalker, ?MeetingPoint $meetingPoint = null): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt), $meetingPoint),
            $firstWalker,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(self::CREATED_AT),
        );
        $this->rounds->add($round);

        return $round;
    }

    private function handler(bool $withActor = true): ListMyRoundsHandler
    {
        return new ListMyRoundsHandler(
            $this->rounds,
            new FixedExecutionContext($withActor ? new Actor($this->jan, []) : null),
            new MockClock(self::NOW),
        );
    }
}
