<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\NotifyBuddyNeeded;

use App\Patrol\Domain\BuddyCaller;
use App\Patrol\Domain\Round;
use App\Patrol\UseCase\NotifyBuddyNeeded\NotifyBuddyNeeded;
use App\Patrol\UseCase\NotifyBuddyNeeded\NotifyBuddyNeededHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Notification;
use App\Tests\Patrol\Domain\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedMemberDirectory;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * The round of RoundFixture starts on 20 November at 22:00 local time and was created on 10 November, so its buddy
 * call is due five days ahead: 15 November at 21:00 in UTC.
 */
final class NotifyBuddyNeededHandlerTest extends TestCase
{
    private const string DUE = '2026-11-15T21:00:00+00:00';
    private const string BEFORE_DUE = '2026-11-15T20:59:59+00:00';

    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private RecordingNotifier $notifier;
    private Uuid $jan;
    private Uuid $piet;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->jan = Uuid::v7();
        $this->piet = Uuid::v7();
    }

    #[Test]
    public function it_calls_the_subscribers_who_do_not_walk_a_due_round_that_seeks_a_buddy(): void
    {
        $round = $this->round(RoundFixture::withWalkers($this->jan, 1));

        $this->handler([$this->jan, $this->piet])(new NotifyBuddyNeeded());

        $this->assertEquals(
            [new Notification($this->piet, 'round.buddy_needed', ['roundId' => $round->id()->toRfc4122()])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_skips_a_round_whose_call_is_not_due(): void
    {
        $round = $this->round(RoundFixture::withWalkers($this->jan, 1));

        $this->handler([$this->piet], now: self::BEFORE_DUE)(new NotifyBuddyNeeded());

        $this->assertSame([[], [], null], [$this->notifier->sent, $this->auditTrail->recorded, $round->buddyCallSentAt()]);
    }

    #[Test]
    public function it_skips_a_staffed_round(): void
    {
        $round = $this->round(RoundFixture::withWalkers($this->jan, 2));

        $this->handler([$this->piet])(new NotifyBuddyNeeded());

        $this->assertSame([[], [], null], [$this->notifier->sent, $this->auditTrail->recorded, $round->buddyCallSentAt()]);
    }

    #[Test]
    public function it_skips_a_round_whose_call_went_out(): void
    {
        $round = RoundFixture::withWalkers($this->jan, 1);
        $round->markBuddyCallSent(new DateTimeImmutable(RoundFixture::CREATED_AT));
        $this->round($round);

        $this->handler([$this->piet])(new NotifyBuddyNeeded());

        $this->assertSame([[], []], [$this->notifier->sent, $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_records_the_buddy_call_with_the_number_of_recipients(): void
    {
        $round = $this->round(RoundFixture::withWalkers($this->jan, 1));

        $this->handler([$this->jan, $this->piet, Uuid::v7()])(new NotifyBuddyNeeded());

        $this->assertEquals(
            [new AuditedAction('round.buddy_call_sent', 'round', $round->id(), ['recipients' => ['old' => null, 'new' => 2]])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $round = $this->round(RoundFixture::withWalkers($this->jan, 1));
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler([$this->piet], authorization: $authorization)(new NotifyBuddyNeeded());
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['round.notify_buddy_needed', null]], $authorization->asked);
        $this->assertSame([[], [], null], [$this->notifier->sent, $this->auditTrail->recorded, $round->buddyCallSentAt()]);
    }

    private function round(Round $round): Round
    {
        $this->rounds->add($round);

        return $round;
    }

    /**
     * @param list<Uuid> $subscribers
     */
    private function handler(array $subscribers, string $now = self::DUE, ?FakeAuthorization $authorization = null): NotifyBuddyNeededHandler
    {
        return new NotifyBuddyNeededHandler(
            $this->rounds,
            new BuddyCaller(new FixedMemberDirectory(openRoundSubscribers: $subscribers), $this->notifier),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock($now),
        );
    }
}
