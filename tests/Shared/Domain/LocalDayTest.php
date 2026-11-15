<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\LocalDay;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * In 2026 summer time starts on 29 March and ends on 25 October, in Europe/Amsterdam.
 */
final class LocalDayTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function days(): iterable
    {
        yield 'an ordinary winter day' => ['2026-01-15T12:00:00+00:00', '2026-01-14T23:00:00+00:00', '2026-01-15T23:00:00+00:00'];
        yield 'the day summer time starts, 23 hours' => ['2026-03-29T12:00:00+00:00', '2026-03-28T23:00:00+00:00', '2026-03-29T22:00:00+00:00'];
        yield 'the day summer time ends, 25 hours' => ['2026-10-25T12:00:00+00:00', '2026-10-24T22:00:00+00:00', '2026-10-25T23:00:00+00:00'];
    }

    #[DataProvider('days')]
    #[Test]
    public function it_runs_from_local_midnight_to_local_midnight(string $instant, string $expectedStart, string $expectedEnd): void
    {
        $day = LocalDay::of(new DateTimeImmutable($instant));

        $this->assertSame(
            [$expectedStart, $expectedEnd],
            [$day->startsAt()->format(\DATE_ATOM), $day->endsAt()->format(\DATE_ATOM)],
        );
    }

    #[Test]
    public function it_takes_the_local_date_of_an_instant_late_in_the_utc_day(): void
    {
        $day = LocalDay::of(new DateTimeImmutable('2026-10-24T22:30:00+00:00'));

        $this->assertSame('2026-10-25', $day->date());
    }

    #[Test]
    public function it_moves_to_the_next_local_day_across_the_clock_change(): void
    {
        $day = LocalDay::of(new DateTimeImmutable('2026-10-24T18:00:00+00:00'));

        $this->assertSame('2026-10-25', $day->next()->date());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function instantsAroundTheDay(): iterable
    {
        yield 'local midnight at the start' => ['2026-10-24T22:00:00+00:00', true];
        yield 'the last minute' => ['2026-10-25T22:59:00+00:00', true];
        yield 'local midnight at the end' => ['2026-10-25T23:00:00+00:00', false];
        yield 'a minute before the start' => ['2026-10-24T21:59:00+00:00', false];
    }

    #[DataProvider('instantsAroundTheDay')]
    #[Test]
    public function it_contains_its_start_but_not_its_end(string $instant, bool $expected): void
    {
        $day = LocalDay::of(new DateTimeImmutable('2026-10-25T12:00:00+00:00'));

        $this->assertSame($expected, $day->contains(new DateTimeImmutable($instant)));
    }
}
