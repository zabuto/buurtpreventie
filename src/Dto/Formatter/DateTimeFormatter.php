<?php declare(strict_types=1);

namespace App\Dto\Formatter;

use DateTimeInterface;

final class DateTimeFormatter
{
    public const string TIMEOFDAY_MORNING = 'morning';
    public const string TIMEOFDAY_AFTERNOON = 'afternoon';
    public const string TIMEOFDAY_EVENING = 'evening';

    public static function format(?DateTimeInterface $value, object $source): ?string
    {
        return $value?->format('d-m-Y H:i');
    }

    public static function date(?DateTimeInterface $value, object $source): ?string
    {
        return $value?->format('d-m-Y');
    }

    public static function time(?DateTimeInterface $value, object $source): ?string
    {
        return $value?->format('H:i');
    }

    public static function timeOfDay(?DateTimeInterface $value, object $source): ?string
    {
        if ($value === null) {
            return null;
        }

        $hour = (int)$value->format('H');

        return match (true) {
            $hour < 12 => self::TIMEOFDAY_MORNING,
            $hour < 18 => self::TIMEOFDAY_AFTERNOON,
            default => self::TIMEOFDAY_EVENING,
        };
    }
}
