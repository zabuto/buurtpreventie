<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\WithdrawMemberFromUpcomingRounds;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRounds;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRoundsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Notification;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class WithdrawMemberFromUpcomingRoundsHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string CREATED_AT = '2026-11-01T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private RecordingNotifier $notifier;
    private Uuid $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->jan = Uuid::v7();
    }

    #[Test]
    public function it_withdraws_the_member_from_every_round_that_has_not_started(): void
    {
        $tomorrow = $this->round('2026-11-16T21:00:00+00:00', $this->jan, Uuid::v7(), Uuid::v7());
        $nextWeek = $this->round('2026-11-22T21:00:00+00:00', Uuid::v7(), $this->jan, Uuid::v7());

        $this->handler()(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));

        $this->assertSame([false, false], [$tomorrow->isSignedUp($this->jan), $nextWeek->isSignedUp($this->jan)]);
    }

    /**
     * Who walked is part of the round's history, also for a round still under way.
     */
    #[Test]
    public function it_keeps_the_member_in_a_round_that_has_started(): void
    {
        $underWay = $this->round('2026-11-15T11:30:00+00:00', $this->jan, Uuid::v7());

        $this->handler()(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));

        $this->assertTrue($underWay->isSignedUp($this->jan));
    }

    #[Test]
    public function it_records_each_automatic_withdrawal(): void
    {
        $round = $this->round('2026-11-16T21:00:00+00:00', $this->jan, Uuid::v7(), Uuid::v7());

        $this->handler()(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));

        $this->assertEquals(
            [
                new AuditedAction('participation.withdrawn_automatically', 'round', $round->id(), [
                    'walker' => ['old' => $this->jan->toRfc4122(), 'new' => null],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_deletes_and_records_a_round_the_member_walked_alone(): void
    {
        $round = $this->round('2026-11-16T21:00:00+00:00', $this->jan);

        $this->handler()(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));

        $this->assertFalse($this->rounds->has($round));
        $this->assertEquals(
            [
                new AuditedAction('participation.withdrawn_automatically', 'round', $round->id(), [
                    'walker' => ['old' => $this->jan->toRfc4122(), 'new' => null],
                ]),
                new AuditedAction('round.deleted', 'round', $round->id()),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_tells_the_remaining_walker_when_the_round_drops_below_the_minimum(): void
    {
        $piet = Uuid::v7();
        $round = $this->round('2026-11-16T21:00:00+00:00', $piet, $this->jan);

        $this->handler()(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));

        $this->assertEquals(
            [new Notification($piet, 'round.walker_withdrawn', ['roundId' => $round->id()->toRfc4122()])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_tells_nobody_when_the_round_keeps_its_minimum(): void
    {
        $this->round('2026-11-16T21:00:00+00:00', Uuid::v7(), Uuid::v7(), $this->jan);

        $this->handler()(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));

        $this->assertSame([], $this->notifier->sent);
    }

    /**
     * The walkers of a round below its minimum see that it seeks a buddy; a withdrawal changes nothing for them.
     */
    #[Test]
    public function it_tells_nobody_when_the_round_was_below_the_minimum_already(): void
    {
        $this->roundWithMinimum('2026-11-16T21:00:00+00:00', 3, Uuid::v7(), $this->jan);

        $this->handler()(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));

        $this->assertSame([], $this->notifier->sent);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $round = $this->round('2026-11-16T21:00:00+00:00', $this->jan, Uuid::v7());
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new WithdrawMemberFromUpcomingRounds($this->jan->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['participation.withdraw_member_from_upcoming', $this->jan]], $authorization->asked);
        $this->assertSame([true, [], []], [$round->isSignedUp($this->jan), $this->auditTrail->recorded, $this->notifier->sent]);
    }

    private function round(string $startsAt, Uuid $firstWalker, Uuid ...$others): Round
    {
        return $this->roundWithMinimum($startsAt, 2, $firstWalker, ...$others);
    }

    private function roundWithMinimum(string $startsAt, int $minimum, Uuid $firstWalker, Uuid ...$others): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            $firstWalker,
            new StaffingRule(minimumWalkers: $minimum, maximumWalkers: 7),
            new DateTimeImmutable(self::CREATED_AT),
        );
        foreach ($others as $walker) {
            $round->signUp($walker, new DateTimeImmutable(self::CREATED_AT));
        }
        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FakeAuthorization $authorization = null): WithdrawMemberFromUpcomingRoundsHandler
    {
        return new WithdrawMemberFromUpcomingRoundsHandler(
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            $this->notifier,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
