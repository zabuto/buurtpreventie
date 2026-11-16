<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListMyRounds;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ListMyRoundsHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string CREATED_AT = '2026-11-01T12:00:00+00:00';

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
    public function it_lists_the_rounds_of_the_member_by_start_with_their_staffing_in_local_time(): void
    {
        $later = $this->round('2026-11-16T21:00:00+00:00', $this->jan, MeetingPoint::create('Speeltuin'));
        $earlier = $this->round('2026-11-14T20:00:00+00:00', $this->piet);
        $earlier->signUp($this->jan, new DateTimeImmutable(self::CREATED_AT));

        $output = $this->handler()(new ListMyRounds());

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

        $output = $this->handler()(new ListMyRounds());

        $this->assertSame([], $output->rounds);
    }

    #[Test]
    public function it_lists_only_the_walked_rounds_when_asked_for_them(): void
    {
        $walked = $this->round('2026-11-14T20:00:00+00:00', $this->jan);
        $walked->signUp($this->piet, new DateTimeImmutable(self::CREATED_AT));
        $this->round('2026-11-13T20:00:00+00:00', $this->jan);
        $this->round('2026-11-16T20:00:00+00:00', $this->jan);

        $output = $this->handler()(new ListMyRounds(walked: true));

        $this->assertSame([$walked->id()->toRfc4122()], \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_lists_every_round_that_is_not_walked_when_asked_for_those(): void
    {
        $this->round('2026-11-14T20:00:00+00:00', $this->jan)->signUp($this->piet, new DateTimeImmutable(self::CREATED_AT));
        $notWalked = $this->round('2026-11-13T20:00:00+00:00', $this->jan);
        $upcoming = $this->round('2026-11-16T20:00:00+00:00', $this->jan);

        $output = $this->handler()(new ListMyRounds(walked: false));

        $this->assertSame([$notWalked->id()->toRfc4122(), $upcoming->id()->toRfc4122()], \array_column($output->rounds, 'id'));
    }

    #[Test]
    public function it_tells_which_walked_round_has_a_report(): void
    {
        $round = $this->round('2026-11-14T20:00:00+00:00', $this->jan);
        $round->signUp($this->piet, new DateTimeImmutable(self::CREATED_AT));
        $round->submitReport($this->piet, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, new DateTimeImmutable(self::NOW));

        $output = $this->handler()(new ListMyRounds());

        $this->assertTrue($output->rounds[0]['reported'] ?? null);
    }

    #[Test]
    public function it_refuses_when_nobody_acts(): void
    {
        $this->expectException(AccessDenied::class);

        $this->handler(withActor: false)(new ListMyRounds());
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
