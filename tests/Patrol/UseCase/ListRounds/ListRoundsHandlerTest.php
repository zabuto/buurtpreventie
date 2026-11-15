<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListRounds;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\UseCase\ListRounds\ListRounds;
use App\Patrol\UseCase\ListRounds\ListRoundsHandler;
use App\Shared\Domain\AccessDenied;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
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
    public function it_refuses_a_period_that_does_not_end_after_its_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler()(new ListRounds('2026-11-16T00:00:00+01:00', '2026-11-16T00:00:00+01:00'));
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

    private function round(RoundStart $start, int $walkers): Round
    {
        $round = Round::plan($start, Uuid::v7(), new StaffingRule(minimumWalkers: 2, maximumWalkers: 3), new DateTimeImmutable(self::CREATED_AT));
        for ($walker = 1; $walker < $walkers; ++$walker) {
            $round->signUp(Uuid::v7(), new DateTimeImmutable(self::CREATED_AT));
        }
        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FakeAuthorization $authorization = null): ListRoundsHandler
    {
        return new ListRoundsHandler($this->rounds, $authorization ?? FakeAuthorization::granting(), new MockClock(self::NOW));
    }
}
