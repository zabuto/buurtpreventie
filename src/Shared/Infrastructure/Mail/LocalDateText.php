<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Domain\LocalTime;
use DateTimeImmutable;
use IntlDateFormatter;

/**
 * A moment as a walker reads it in a mail: the day written out and the time on the local clock, whatever the zone of
 * the server.
 */
final readonly class LocalDateText
{
    /**
     * @return string such as "vrijdag 20 november" or "Friday 20 November"
     */
    public static function date(DateTimeImmutable $moment, string $locale): string
    {
        return self::format($moment, $locale, 'EEEE d MMMM');
    }

    /**
     * @return string such as "zaterdag 13 februari 2027", for a moment that may lie in another year
     */
    public static function dateWithYear(DateTimeImmutable $moment, string $locale): string
    {
        return self::format($moment, $locale, 'EEEE d MMMM y');
    }

    /**
     * @return string such as "22:00"
     */
    public static function time(DateTimeImmutable $moment, string $locale): string
    {
        return self::format($moment, $locale, 'HH:mm');
    }

    private static function format(DateTimeImmutable $moment, string $locale, string $pattern): string
    {
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, LocalTime::ZONE, IntlDateFormatter::GREGORIAN, $pattern);

        return (string) $formatter->format($moment);
    }
}
