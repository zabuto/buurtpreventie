<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Statistics;

use App\Patrol\Domain\Round\Daypart;
use App\Shared\Domain\BackedEnumValues;
use App\Shared\Domain\LocalTime;
use DateTimeImmutable;

/**
 * How the statistics split a period: by local month, weekday or daypart, as the members know them.
 */
enum StatisticsGrouping: string
{
    use BackedEnumValues;

    case Month = 'month';
    case Weekday = 'weekday';
    case Daypart = 'daypart';

    private const array WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function groupOf(DateTimeImmutable $startsAt): string
    {
        return match ($this) {
            self::Month => LocalTime::of($startsAt)->format('Y-m'),
            self::Weekday => \strtolower(LocalTime::of($startsAt)->format('l')),
            self::Daypart => Daypart::of($startsAt)->value,
        };
    }

    /**
     * Every group of the period in order, also those without a round, so a gap shows as a zero.
     *
     * @return list<string>
     */
    public function groupsIn(StatisticsPeriod $period): array
    {
        return match ($this) {
            self::Month => self::months($period),
            self::Weekday => self::WEEKDAYS,
            self::Daypart => \array_map(static fn(Daypart $daypart): string => $daypart->value, Daypart::cases()),
        };
    }

    /**
     * @return list<string>
     */
    private static function months(StatisticsPeriod $period): array
    {
        $last = LocalTime::of($period->to->modify('-1 second'))->format('Y-m');
        $month = LocalTime::of($period->from)->modify('first day of this month midnight');
        $months = [];
        do {
            $months[] = $month->format('Y-m');
            $month = $month->modify('first day of next month');
        } while ($month->format('Y-m') <= $last);

        return $months;
    }
}
