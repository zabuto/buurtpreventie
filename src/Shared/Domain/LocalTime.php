<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The clock the walkers live by. Instants are stored in UTC; dayparts, calendar days and everything a walker reads
 * follow this time zone, whatever the time zone of the server.
 */
final readonly class LocalTime
{
    public const string ZONE = 'Europe/Amsterdam';

    public static function of(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone(self::ZONE));
    }
}
