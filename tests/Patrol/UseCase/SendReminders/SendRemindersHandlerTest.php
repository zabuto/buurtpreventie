<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\SendReminders;

use App\Patrol\Domain\Round\Reminders;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\SendReminders\SendReminders;
use App\Patrol\UseCase\SendReminders\SendRemindersHandler;
use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetReminderLeadTime\GetReminderLeadTimeHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\Notification;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Settings\Fake\InMemorySettingRepository;
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
 * NOW is 15 November 2026 at 13:00 in Europe/Amsterdam, so tomorrow runs from 15 November 23:00 up to 16 November
 * 23:00 in UTC.
 */
final class SendRemindersHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string CREATED_AT = '2026-10-01T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private RecordingNotifier $notifier;
    private InMemorySettingRepository $settings;
    private Uuid $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->settings = new InMemorySettingRepository();
        $this->jan = Uuid::v7();
    }

    #[Test]
    public function it_reminds_as_many_days_ahead_as_the_association_set(): void
    {
        $this->settings->add(Setting::of('patrol.reminder_days_ahead', '3'));
        $this->round('2026-11-16T20:00:00+00:00', 1, $this->jan);
        $inThreeDays = $this->round('2026-11-18T20:00:00+00:00', 1, $this->jan);

        $this->handler([$this->jan])(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', ['roundIds' => $inThreeDays->id()->toRfc4122(), 'seekingBuddyRoundIds' => ''])],
            $this->notifier->sent,
        );
    }

    /**
     * Review focus 2: three days ahead of 25 March 2027 is Sunday 28 March, the day the clock goes forward and lasts
     * 23 hours; a round at 22:00 local starts at 20:00 UTC that day.
     */
    #[Test]
    public function it_counts_days_ahead_on_the_local_clock_across_the_clock_change(): void
    {
        $this->settings->add(Setting::of('patrol.reminder_days_ahead', '3'));
        $this->round('2027-03-27T22:59:00+00:00', 1, $this->jan);
        $atTen = $this->round('2027-03-28T20:00:00+00:00', 1, $this->jan);
        $this->round('2027-03-28T22:00:00+00:00', 1, $this->jan);

        $this->handler([$this->jan], now: '2027-03-25T10:00:00+00:00')(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', ['roundIds' => $atTen->id()->toRfc4122(), 'seekingBuddyRoundIds' => ''])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_sends_a_walker_one_reminder_naming_all_their_rounds_of_tomorrow(): void
    {
        $evening = $this->round('2026-11-16T20:00:00+00:00', 1, $this->jan);
        $morning = $this->round('2026-11-16T08:00:00+00:00', 1, $this->jan);

        $this->handler([$this->jan])(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', [
                'roundIds' => $morning->id()->toRfc4122() . ',' . $evening->id()->toRfc4122(),
                'seekingBuddyRoundIds' => '',
            ])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_sends_no_reminder_to_an_inactive_walker(): void
    {
        $piet = Uuid::v7();
        $round = $this->round('2026-11-16T20:00:00+00:00', 2, $this->jan, $piet);

        $this->handler([$this->jan], inactive: [$piet])(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', ['roundIds' => $round->id()->toRfc4122(), 'seekingBuddyRoundIds' => ''])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_leaves_out_the_rounds_of_today_and_of_the_day_after_tomorrow(): void
    {
        $this->round('2026-11-15T20:00:00+00:00', 1, $this->jan);
        $tomorrow = $this->round('2026-11-16T20:00:00+00:00', 1, $this->jan);
        $this->round('2026-11-17T08:00:00+00:00', 1, $this->jan);

        $this->handler([$this->jan])(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', ['roundIds' => $tomorrow->id()->toRfc4122(), 'seekingBuddyRoundIds' => ''])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_includes_the_rounds_from_local_midnight_up_to_the_next_local_midnight(): void
    {
        $this->round('2026-11-15T22:59:00+00:00', 1, $this->jan);
        $afterMidnight = $this->round('2026-11-15T23:00:00+00:00', 1, $this->jan);
        $beforeMidnight = $this->round('2026-11-16T22:59:00+00:00', 1, $this->jan);
        $this->round('2026-11-16T23:00:00+00:00', 1, $this->jan);

        $this->handler([$this->jan])(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', [
                'roundIds' => $afterMidnight->id()->toRfc4122() . ',' . $beforeMidnight->id()->toRfc4122(),
                'seekingBuddyRoundIds' => '',
            ])],
            $this->notifier->sent,
        );
    }

    /**
     * On 25 October 2026 the clock goes back an hour: the day runs from 22:00 on 24 October up to 23:00 on 25 October
     * in UTC, 25 hours.
     */
    #[Test]
    public function it_includes_the_whole_local_day_when_the_clock_changes(): void
    {
        $this->round('2026-10-24T21:59:00+00:00', 1, $this->jan);
        $afterMidnight = $this->round('2026-10-24T22:00:00+00:00', 1, $this->jan);
        $beforeMidnight = $this->round('2026-10-25T22:59:00+00:00', 1, $this->jan);
        $this->round('2026-10-25T23:00:00+00:00', 1, $this->jan);

        $this->handler([$this->jan], now: '2026-10-24T10:00:00+00:00')(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', [
                'roundIds' => $afterMidnight->id()->toRfc4122() . ',' . $beforeMidnight->id()->toRfc4122(),
                'seekingBuddyRoundIds' => '',
            ])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_names_only_the_rounds_below_their_minimum_as_seeking_a_buddy(): void
    {
        $staffed = $this->round('2026-11-16T08:00:00+00:00', 1, $this->jan);
        $seeking = $this->round('2026-11-16T20:00:00+00:00', 2, $this->jan);

        $this->handler([$this->jan])(new SendReminders());

        $this->assertEquals(
            [new Notification($this->jan, 'round.reminder', [
                'roundIds' => $staffed->id()->toRfc4122() . ',' . $seeking->id()->toRfc4122(),
                'seekingBuddyRoundIds' => $seeking->id()->toRfc4122(),
            ])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_marks_each_reminded_participation_as_reminded(): void
    {
        $morning = $this->round('2026-11-16T08:00:00+00:00', 1, $this->jan);
        $evening = $this->round('2026-11-16T20:00:00+00:00', 1, $this->jan);

        $this->handler([$this->jan])(new SendReminders());

        $this->assertEquals(
            [new DateTimeImmutable(self::NOW), new DateTimeImmutable(self::NOW)],
            [$morning->participationOf($this->jan)?->remindedAt(), $evening->participationOf($this->jan)?->remindedAt()],
        );
    }

    #[Test]
    public function it_sends_nothing_when_it_runs_a_second_time_on_the_same_day(): void
    {
        $this->round('2026-11-16T20:00:00+00:00', 1, $this->jan);
        $handler = $this->handler([$this->jan]);
        $handler(new SendReminders());
        $this->notifier->sent = [];

        $handler(new SendReminders());

        $this->assertSame([], $this->notifier->sent);
    }

    #[Test]
    public function it_records_each_reminder_per_round_and_walker(): void
    {
        $piet = Uuid::v7();
        $morning = $this->round('2026-11-16T08:00:00+00:00', 1, $this->jan);
        $evening = $this->round('2026-11-16T20:00:00+00:00', 2, $this->jan, $piet);

        $this->handler([$this->jan, $piet])(new SendReminders());

        $this->assertEquals(
            [
                new AuditedAction('participation.reminded', 'round', $morning->id(), [
                    'walker' => ['old' => null, 'new' => $this->jan->toRfc4122()],
                ]),
                new AuditedAction('participation.reminded', 'round', $evening->id(), [
                    'walker' => ['old' => null, 'new' => $this->jan->toRfc4122()],
                ]),
                new AuditedAction('participation.reminded', 'round', $evening->id(), [
                    'walker' => ['old' => null, 'new' => $piet->toRfc4122()],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $round = $this->round('2026-11-16T20:00:00+00:00', 1, $this->jan);
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler([$this->jan], authorization: $authorization)(new SendReminders());
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['round.send_reminders', null]], $authorization->asked);
        $this->assertSame(
            [[], [], null],
            [$this->notifier->sent, $this->auditTrail->recorded, $round->participationOf($this->jan)?->remindedAt()],
        );
    }

    private function round(string $startsAt, int $minimum, Uuid $firstWalker, Uuid ...$others): Round
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

    /**
     * @param list<Uuid> $active
     * @param list<Uuid> $inactive
     */
    private function handler(
        array $active,
        array $inactive = [],
        string $now = self::NOW,
        ?FakeAuthorization $authorization = null,
    ): SendRemindersHandler {
        $profiles = [
            ...\array_map(FixedMemberDirectory::activeProfile(...), $active),
            ...\array_map(static fn(Uuid $memberId): MemberProfile => new MemberProfile($memberId, 'Piet V.', 'Piet Visser', null, active: false), $inactive),
        ];

        return new SendRemindersHandler(
            $this->rounds,
            new FixedMemberDirectory($profiles),
            new Reminders(),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            $this->notifier,
            new ImmediateTransaction(),
            new MockClock($now),
            new GetReminderLeadTimeHandler($this->settings),
        );
    }
}
