<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\FindStaffingGaps;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\FindStaffingGaps\FindStaffingGaps;
use App\Patrol\UseCase\FindStaffingGaps\FindStaffingGapsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class FindStaffingGapsHandlerTest extends TestCase
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
    public function it_describes_a_gap_with_the_walkers_it_needs_in_local_time(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), MeetingPoint::create('Speeltuin')), walkers: 1);

        $output = $this->handler()(new FindStaffingGaps());

        $this->assertSame(
            [
                [
                    'id' => $round->id()->toRfc4122(),
                    'startsAt' => '2026-11-16T09:00:00+01:00',
                    'daypart' => 'morning',
                    'status' => 'seeking_buddy',
                    'walkerCount' => 1,
                    'minimumWalkers' => 2,
                    'maximumWalkers' => 3,
                    'walkersNeeded' => 1,
                    'openSpots' => 2,
                    'meetingPoint' => 'Speeltuin',
                    'signedUp' => false,
                ],
            ],
            $output->gaps,
        );
    }

    /**
     * The maximum is never below the minimum, so a round with no open spots always has enough walkers.
     */
    #[Test]
    public function it_finds_no_gap_in_a_full_or_almost_full_round(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 3);
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-17T21:00:00+00:00')), walkers: 2);
        $gap = $this->round(new RoundStart(new DateTimeImmutable('2026-11-18T21:00:00+00:00')), walkers: 1);

        $output = $this->handler()(new FindStaffingGaps());

        $this->assertSame([$gap->id()->toRfc4122()], \array_column($output->gaps, 'id'));
    }

    #[Test]
    public function it_finds_no_gap_in_a_round_that_has_started(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-15T11:30:00+00:00')), walkers: 1);

        $output = $this->handler()(new FindStaffingGaps());

        $this->assertSame([], $output->gaps);
    }

    #[Test]
    public function it_lists_the_gaps_by_start(): void
    {
        $later = $this->round(new RoundStart(new DateTimeImmutable('2026-11-18T21:00:00+00:00')), walkers: 1);
        $sooner = $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 1);

        $output = $this->handler()(new FindStaffingGaps());

        $this->assertSame([$sooner->id()->toRfc4122(), $later->id()->toRfc4122()], \array_column($output->gaps, 'id'));
    }

    #[Test]
    public function it_finds_only_the_gaps_that_start_within_the_number_of_days_asked(): void
    {
        $tomorrow = $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T11:59:00+00:00')), walkers: 1);
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T12:00:00+00:00')), walkers: 1);

        $output = $this->handler()(new FindStaffingGaps(days: 1));

        $this->assertSame([$tomorrow->id()->toRfc4122()], \array_column($output->gaps, 'id'));
    }

    #[Test]
    public function it_says_for_each_round_whether_the_member_asking_walks_it(): void
    {
        $jan = Uuid::v7();
        $own = $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T20:00:00+00:00')), walkers: 1, firstWalker: $jan);
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 1);

        $output = $this->handler(actor: new Actor($jan, [Role::Walker]))(new FindStaffingGaps());

        $this->assertSame([true, false], \array_column($output->gaps, 'signedUp'));
        $this->assertSame($own->id()->toRfc4122(), $output->gaps[0]['id'] ?? null);
    }

    #[Test]
    public function it_says_the_system_walks_no_round(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T20:00:00+00:00')), walkers: 1);

        $output = $this->handler()(new FindStaffingGaps());

        $this->assertSame([false], \array_column($output->gaps, 'signedUp'));
    }

    #[Test]
    public function it_returns_nothing_when_access_is_denied(): void
    {
        $this->round(new RoundStart(new DateTimeImmutable('2026-11-16T21:00:00+00:00')), walkers: 1);
        $authorization = FakeAuthorization::refusing();

        $output = null;
        $refusal = null;
        try {
            $output = $this->handler($authorization)(new FindStaffingGaps());
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

    private function handler(?FakeAuthorization $authorization = null, ?Actor $actor = null): FindStaffingGapsHandler
    {
        return new FindStaffingGapsHandler(
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            new FixedExecutionContext($actor),
            new MockClock(self::NOW),
        );
    }
}
