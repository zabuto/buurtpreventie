<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Round;

use App\Patrol\Domain\Round\Daypart;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DaypartTest extends TestCase
{
    /**
     * Every instant is given in UTC; the expected daypart follows the clock in Europe/Amsterdam.
     *
     * @return iterable<string, array{string, Daypart}>
     */
    public static function instants(): iterable
    {
        yield '11:59 summer time' => ['2026-07-01T09:59:00+00:00', Daypart::Morning];
        yield '12:00 summer time, 10:00 in UTC' => ['2026-07-01T10:00:00+00:00', Daypart::Afternoon];
        yield '17:59 summer time' => ['2026-07-01T15:59:00+00:00', Daypart::Afternoon];
        yield '18:00 summer time, 16:00 in UTC' => ['2026-07-01T16:00:00+00:00', Daypart::Evening];
        yield '18:00 winter time, 17:00 in UTC' => ['2026-01-15T17:00:00+00:00', Daypart::Evening];
        yield '23:59 winter time' => ['2026-01-15T22:59:00+00:00', Daypart::Evening];
        yield '00:30 winter time, 23:30 the day before in UTC' => ['2026-01-15T23:30:00+00:00', Daypart::Morning];
    }

    #[DataProvider('instants')]
    #[Test]
    public function it_takes_the_daypart_from_local_time(string $instant, Daypart $expected): void
    {
        $daypart = Daypart::of(new DateTimeImmutable($instant));

        $this->assertSame($expected, $daypart);
    }
}
