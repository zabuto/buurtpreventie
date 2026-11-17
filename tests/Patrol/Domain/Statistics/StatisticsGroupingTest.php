<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Statistics;

use App\Patrol\Domain\Statistics\StatisticsGrouping;
use App\Patrol\Domain\Statistics\StatisticsPeriod;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatisticsGroupingTest extends TestCase
{
    /**
     * @return iterable<string, array{StatisticsGrouping, string, string}>
     */
    public static function starts(): iterable
    {
        yield 'month in local time, the last evening of October' => [StatisticsGrouping::Month, '2026-10-31T23:30:00+00:00', '2026-11'];
        yield 'weekday in local time, Sunday night after midnight' => [StatisticsGrouping::Weekday, '2026-11-15T23:30:00+00:00', 'monday'];
        yield 'daypart, 22:00 after the clocks went back' => [StatisticsGrouping::Daypart, '2026-10-25T21:00:00+00:00', 'evening'];
        yield 'daypart, 11:59 local' => [StatisticsGrouping::Daypart, '2026-11-16T10:59:00+00:00', 'morning'];
    }

    #[DataProvider('starts')]
    #[Test]
    public function it_puts_a_start_in_its_local_group(StatisticsGrouping $grouping, string $startsAt, string $group): void
    {
        $this->assertSame($group, $grouping->groupOf(new DateTimeImmutable($startsAt)));
    }

    #[Test]
    public function it_lists_every_month_the_period_touches(): void
    {
        $period = new StatisticsPeriod(new DateTimeImmutable('2026-10-01T00:00:00+02:00'), new DateTimeImmutable('2027-01-01T00:00:00+01:00'));

        $this->assertSame(['2026-10', '2026-11', '2026-12'], StatisticsGrouping::Month->groupsIn($period));
    }

    #[Test]
    public function it_lists_the_weekdays_from_monday_and_the_dayparts_from_morning(): void
    {
        $period = new StatisticsPeriod(new DateTimeImmutable('2026-11-18T00:00:00+01:00'), new DateTimeImmutable('2026-11-19T00:00:00+01:00'));

        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], StatisticsGrouping::Weekday->groupsIn($period));
        $this->assertSame(['morning', 'afternoon', 'evening'], StatisticsGrouping::Daypart->groupsIn($period));
    }
}
