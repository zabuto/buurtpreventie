<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\BuddyCaller;
use App\Shared\Domain\Notification;
use App\Tests\Shared\Fake\FixedMemberDirectory;
use App\Tests\Shared\Fake\RecordingNotifier;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The round of RoundFixture starts on 20 November at 22:00 local time and was created on 10 November, so its buddy
 * call is due five days ahead: 15 November at 22:00 local time, 21:00 in UTC.
 */
final class BuddyCallerTest extends TestCase
{
    private const string DUE = '2026-11-15T21:00:00+00:00';
    private const string BEFORE_DUE = '2026-11-15T20:59:59+00:00';

    #[Test]
    public function it_calls_every_subscriber_who_does_not_walk_the_round_yet(): void
    {
        $owner = Uuid::v7();
        $piet = Uuid::v7();
        $round = RoundFixture::withWalkers($owner, 1);
        $notifier = new RecordingNotifier();

        $recipients = new BuddyCaller(new FixedMemberDirectory(openRoundSubscribers: [$owner, $piet]), $notifier)->callIfDue(
            $round,
            new DateTimeImmutable(self::DUE),
        );

        $this->assertSame(1, $recipients);
        $this->assertEquals([new Notification($piet, 'round.buddy_needed', ['roundId' => $round->id()->toRfc4122()])], $notifier->sent);
        $this->assertEquals(new DateTimeImmutable(self::DUE), $round->buddyCallSentAt());
    }

    #[Test]
    public function it_calls_nobody_before_the_call_is_due(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);
        $notifier = new RecordingNotifier();

        $recipients = new BuddyCaller(new FixedMemberDirectory(openRoundSubscribers: [Uuid::v7()]), $notifier)->callIfDue(
            $round,
            new DateTimeImmutable(self::BEFORE_DUE),
        );

        $this->assertSame([null, [], null], [$recipients, $notifier->sent, $round->buddyCallSentAt()]);
    }

    /**
     * Without subscribers the call counts as made, so the scheduler does not weigh the round on every run.
     */
    #[Test]
    public function it_marks_the_call_as_made_when_nobody_subscribes(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);

        $recipients = new BuddyCaller(new FixedMemberDirectory(), new RecordingNotifier())->callIfDue($round, new DateTimeImmutable(self::DUE));

        $this->assertSame([0, self::DUE], [$recipients, $round->buddyCallSentAt()?->format(\DATE_ATOM)]);
    }

    #[Test]
    public function it_calls_only_once_per_round(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);
        $notifier = new RecordingNotifier();
        $caller = new BuddyCaller(new FixedMemberDirectory(openRoundSubscribers: [Uuid::v7()]), $notifier);
        $caller->callIfDue($round, new DateTimeImmutable(self::DUE));

        $recipients = $caller->callIfDue($round, new DateTimeImmutable('2026-11-17T21:00:00+00:00'));

        $this->assertSame([null, 1], [$recipients, \count($notifier->sent)]);
    }
}
