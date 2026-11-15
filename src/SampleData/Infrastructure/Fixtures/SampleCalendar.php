<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Shared\Domain\LocalTime;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Dates relative to today on the local clock, so a round set at 21:00 stays at 21:00 across a clock change.
 */
final readonly class SampleCalendar
{
    /**
     * @param string $time hours and minutes, such as 21:00
     */
    public static function day(DateTimeImmutable $now, int $days, string $time): DateTimeImmutable
    {
        [$hours, $minutes] = \array_map(\intval(...), \explode(':', $time));

        return $now
            ->setTimezone(new DateTimeZone(LocalTime::ZONE))
            ->setTime(0, 0)
            ->modify(\sprintf('%+d days', $days))
            ->setTime($hours, $minutes)
        ;
    }
}
