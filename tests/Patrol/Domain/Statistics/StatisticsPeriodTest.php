<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Statistics;

use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Statistics\StatisticsPeriod;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatisticsPeriodTest extends TestCase
{
    #[Test]
    public function it_takes_a_calendar_year_in_local_time(): void
    {
        $period = new StatisticsPeriod(new DateTimeImmutable('2028-01-01T00:00:00+01:00'), new DateTimeImmutable('2029-01-01T00:00:00+01:00'));

        $this->assertSame('2029-01-01T00:00:00+01:00', $period->to->format(\DATE_ATOM));
    }

    #[Test]
    public function it_refuses_a_period_of_more_than_366_days(): void
    {
        $this->expectException(InvalidPeriod::class);

        new StatisticsPeriod(new DateTimeImmutable('2026-01-01T00:00:00+01:00'), new DateTimeImmutable('2027-01-02T00:00:01+01:00'));
    }

    #[Test]
    public function it_refuses_a_period_that_does_not_end_after_its_start(): void
    {
        $this->expectException(InvalidPeriod::class);

        new StatisticsPeriod(new DateTimeImmutable('2026-11-01T00:00:00+01:00'), new DateTimeImmutable('2026-11-01T00:00:00+01:00'));
    }

    #[Test]
    public function it_compares_with_the_month_before_for_a_month(): void
    {
        $october = new StatisticsPeriod(new DateTimeImmutable('2026-10-01T00:00:00+02:00'), new DateTimeImmutable('2026-11-01T00:00:00+01:00'));

        $previous = $october->previous();

        $this->assertSame(['2026-09-01T00:00:00+02:00', '2026-10-01T00:00:00+02:00'], [$previous->from->format(\DATE_ATOM), $previous->to->format(\DATE_ATOM)]);
    }

    #[Test]
    public function it_compares_with_an_equally_long_period_right_before_otherwise(): void
    {
        $week = new StatisticsPeriod(new DateTimeImmutable('2026-11-16T00:00:00+01:00'), new DateTimeImmutable('2026-11-23T00:00:00+01:00'));

        $previous = $week->previous();

        $this->assertSame(['2026-11-09T00:00:00+01:00', '2026-11-16T00:00:00+01:00'], [$previous->from->format(\DATE_ATOM), $previous->to->format(\DATE_ATOM)]);
    }
}
