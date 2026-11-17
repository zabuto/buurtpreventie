<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListRounds;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\ListRounds\ListRounds;
use App\Patrol\UseCase\ListRounds\ListRoundsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ListRoundsHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string CREATED_AT = '2026-11-01T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
    }

    #[Test]
    public function it_lists_each_round_with_its_staffing_in_local_time(): void
    {
        $speeltuin = MeetingPoint::create('Speeltuin');
        $round = $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00'), $speeltuin), walkers: 1);

        $output = $this->handler()(new ListRounds('2026-11-16T00:00:00+01:00', '2026-11-17T00:00:00+01:00'));

        $this->assertSame(
            [
                [
                    'id' => $round->id()->toRfc4122(),
                    'startsAt' => '2026-11-16T22:00:00+01:00',
                    'daypart' => 'evening',
                    'status' => 'seeking_buddy',
                    'walkerCount' => 1,
                    'minimumWalkers' => 2,
                    'maximumWalkers' => 3,
                    'openSpots' => 2,
                    'meetingPoint' => 'Speeltuin',
                    'signedUp' => false,
                ],
            ],
            $output->rounds,
        );
    }

    #[Test]
    public function it_lists_only_the_rounds_in_the_period_by_start(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-15T22:59:00+00:00')), walkers: 1);
        $late = $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 1);
        $early = $this->round(new RoundStart(new DateTimeImmutable('2026-11-15T23:00:00+00:00')), walkers: 1);
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T23:00:00+00:00')), walkers: 1);

        $output = $this->handler()(new ListRounds('2026-11-16T00:00:00+01:00', '2026-11-17T00:00:00+01:00'));

        $this->assertSame([$early->id()->toRfc4122(), $late->id()->toRfc4122()], \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_lists_only_the_rounds_with_the_requested_status(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-14T21:00:00+00:00')), walkers: 1);
        $walked = $this->round(new RoundStart(new DateTimeImmutable('2026-11-14T22:00:00+00:00')), walkers: 2);
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 2);

        $output = $this->handler()(new ListRounds('2026-11-14T00:00:00+01:00', '2026-11-17T00:00:00+01:00', status: 'walked'));

        $this->assertSame([$walked->id()->toRfc4122()], \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_lists_a_cancelled_round_as_cancelled_and_not_as_walked(): void
    {
        $cancelled = $this->round(new RoundStart(new DateTimeImmutable('2026-11-14T22:00:00+00:00')), walkers: 2);
        $cancelled->cancel(null, new DateTimeImmutable('2026-11-14T22:30:00+00:00'));

        $walked = $this->handler()(new ListRounds('2026-11-14T00:00:00+01:00', '2026-11-17T00:00:00+01:00', status: 'walked'));
        $found = $this->handler()(new ListRounds('2026-11-14T00:00:00+01:00', '2026-11-17T00:00:00+01:00', status: 'cancelled'));

        $this->assertSame([[], [$cancelled->id()->toRfc4122()]], [\array_column($walked->rounds, 'id'), \array_column($found->rounds, 'id')]);
    }

    #[Test]
    public function it_refuses_a_period_that_does_not_end_after_its_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler()(new ListRounds('2026-11-16T00:00:00+01:00', '2026-11-16T00:00:00+01:00'));
    }

    #[Test]
    public function it_refuses_a_period_longer_than_93_days(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler()(new ListRounds('2026-10-01T00:00:00+00:00', '2027-01-02T00:00:01+00:00'));
    }

    /**
     * The longest quarter in local time lasts 92 days and the hour the clocks go back, so it fits.
     */
    #[Test]
    public function it_lists_a_period_of_exactly_93_days(): void
    {
        $output = $this->handler()(new ListRounds('2026-10-01T00:00:00+00:00', '2027-01-02T00:00:00+00:00'));

        $this->assertSame([], $output->rounds);
    }

    #[Test]
    public function it_says_for_each_round_whether_the_member_asking_walks_it(): void
    {
        $jan = Uuid::v7();
        $own = $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T20:00:00+00:00')), walkers: 1, firstWalker: $jan);
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 1);

        $output = $this->handler(actor: new Actor($jan, [Role::Walker]))(new ListRounds('2026-11-16T00:00:00+01:00', '2026-11-17T00:00:00+01:00'));

        $this->assertSame([true, false], \array_column($output->rounds, 'signedUp'));
        $this->assertSame($own->id()->toRfc4122(), $output->rounds[0]['id'] ?? null);
    }

    #[Test]
    public function it_says_the_system_walks_no_round(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T20:00:00+00:00')), walkers: 1);

        $output = $this->handler()(new ListRounds('2026-11-16T00:00:00+01:00', '2026-11-17T00:00:00+01:00'));

        $this->assertSame([false], \array_column($output->rounds, 'signedUp'));
    }

    #[Test]
    public function it_returns_nothing_when_access_is_denied(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 1);
        $authorization = FakeAuthorization::refusing();

        $output = null;
        $refusal = null;
        try {
            $output = $this->handler($authorization)(new ListRounds('2026-11-16T00:00:00+01:00', '2026-11-17T00:00:00+01:00'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertNull($output);
        $this->assertSame([['round.list', null]], $authorization->asked);
    }

    private function round(RoundStart $start, int $walkers, ?Uuid $firstWalker = null): Round
    {
        $round = Round::plan(
            $start,
            $firstWalker ?? Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(self::CREATED_AT),
        );
        for ($walker = 1; $walker < $walkers; ++$walker) {
            $round->signUp(Uuid::v7(), new DateTimeImmutable(self::CREATED_AT));
        }
        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FakeAuthorization $authorization = null, ?Actor $actor = null): ListRoundsHandler
    {
        return new ListRoundsHandler(
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            new FixedExecutionContext($actor),
            new MockClock(self::NOW),
        );
    }
}
