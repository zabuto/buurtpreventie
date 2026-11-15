<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Reminder;
use App\Patrol\Domain\Reminders;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RemindersTest extends TestCase
{
    private const string NOW = '2026-11-19T17:00:00+00:00';

    #[Test]
    public function it_reminds_each_active_walker_once(): void
    {
        $walker = Uuid::v7();
        $early = $this->round('2026-11-20T18:00:00+00:00', $walker);
        $late = $this->round('2026-11-20T21:00:00+00:00', $walker);

        $reminders = new Reminders()->due([$early, $late], activeMemberIds: [$walker]);

        $this->assertCount(1, $reminders);
        $this->assertEquals($walker, $reminders[0]->memberId);
        $this->assertSame([$early, $late], $reminders[0]->rounds);
    }

    #[Test]
    public function it_skips_an_inactive_walker(): void
    {
        $active = Uuid::v7();
        $round = $this->round('2026-11-20T21:00:00+00:00', $active);
        $round->signUp(Uuid::v7(), new DateTimeImmutable(self::NOW));

        $reminders = new Reminders()->due([$round], activeMemberIds: [$active]);

        $this->assertSame(
            [$active->toRfc4122()],
            \array_map(static fn(Reminder $reminder): string => $reminder->memberId->toRfc4122(), $reminders),
        );
    }

    #[Test]
    public function it_does_not_remind_a_walker_twice(): void
    {
        $walker = Uuid::v7();
        $round = $this->round('2026-11-20T21:00:00+00:00', $walker);

        $round->markReminded($walker, new DateTimeImmutable(self::NOW));

        $this->assertSame([], new Reminders()->due([$round], activeMemberIds: [$walker]));
    }

    #[Test]
    public function it_reminds_per_local_calendar_day(): void
    {
        $walker = Uuid::v7();
        $beforeMidnight = $this->round('2026-11-20T22:30:00+00:00', $walker);
        $afterMidnight = $this->round('2026-11-20T23:30:00+00:00', $walker);

        $reminders = new Reminders()->due([$beforeMidnight, $afterMidnight], activeMemberIds: [$walker]);

        $this->assertSame(
            [[$beforeMidnight], [$afterMidnight]],
            \array_map(static fn(Reminder $reminder): array => $reminder->rounds, $reminders),
        );
    }

    private function round(string $startsAt, Uuid $firstWalker): Round
    {
        return Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            $firstWalker,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable(self::NOW),
        );
    }
}
