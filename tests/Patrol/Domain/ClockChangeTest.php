<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Daypart;
use App\Patrol\Domain\Reminders;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Shared\Domain\LocalDay;
use App\Shared\Domain\LocalTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A round at 22:00 stays at 22:00 for the walker on the days around both clock changes. In 2026 summer time starts
 * on 29 March and ends on 25 October, in Europe/Amsterdam.
 */
final class ClockChangeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function eveningsAroundTheClockChanges(): iterable
    {
        yield 'the evening before summer time' => ['2026-03-28', '2026-03-28T21:00:00+00:00'];
        yield 'the first evening of summer time' => ['2026-03-29', '2026-03-29T20:00:00+00:00'];
        yield 'the last evening of summer time' => ['2026-10-24', '2026-10-24T20:00:00+00:00'];
        yield 'the first evening of winter time' => ['2026-10-25', '2026-10-25T21:00:00+00:00'];
    }

    #[DataProvider('eveningsAroundTheClockChanges')]
    #[Test]
    public function it_keeps_a_round_at_ten_in_the_evening(string $date, string $expectedUtc): void
    {
        $round = $this->roundAtTenInTheEvening($date);

        $this->assertSame($expectedUtc, $round->startsAt()->format(\DATE_ATOM));
        $this->assertSame($date . ' 22:00', LocalTime::of($round->startsAt())->format('Y-m-d H:i'));
        $this->assertSame(Daypart::Evening, $round->daypart());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function remindersTheDayBefore(): iterable
    {
        yield 'summer time starts on the day of the round' => ['2026-03-28T18:00:00+00:00', '2026-03-29'];
        yield 'winter time starts on the day of the round' => ['2026-10-24T18:00:00+00:00', '2026-10-25'];
        yield 'summer time starts on the day of the reminder' => ['2026-03-29T18:00:00+00:00', '2026-03-30'];
        yield 'winter time starts on the day of the reminder' => ['2026-10-25T18:00:00+00:00', '2026-10-26'];
    }

    #[DataProvider('remindersTheDayBefore')]
    #[Test]
    public function it_reminds_a_walker_on_the_local_day_before_the_round(string $now, string $roundDate): void
    {
        $walker = Uuid::v7();
        $tomorrow = LocalDay::of(new DateTimeImmutable($now))->next();
        $rounds = \array_values(\array_filter(
            [$this->roundAtTenInTheEvening($roundDate, $walker), $this->roundAtTenInTheEvening($tomorrow->next()->date(), $walker)],
            static fn(Round $round): bool => $tomorrow->contains($round->startsAt()),
        ));

        $reminders = new Reminders()->due($rounds, activeMemberIds: [$walker]);

        $this->assertCount(1, $reminders);
        $this->assertSame([$roundDate], \array_map(
            static fn(Round $round): string => LocalTime::of($round->startsAt())->format('Y-m-d'),
            $reminders[0]->rounds,
        ));
    }

    private function roundAtTenInTheEvening(string $date, ?Uuid $firstWalker = null): Round
    {
        return Round::plan(
            new RoundStart(new DateTimeImmutable($date . 'T22:00:00', new DateTimeZone('Europe/Amsterdam'))),
            $firstWalker ?? Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
        );
    }
}
