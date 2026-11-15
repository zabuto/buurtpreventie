<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\LocalTime;
use DateTimeImmutable;

enum Daypart: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';

    public static function of(DateTimeImmutable $instant): self
    {
        $hour = (int) LocalTime::of($instant)->format('G');

        return match (true) {
            $hour < 12 => self::Morning,
            $hour < 18 => self::Afternoon,
            default => self::Evening,
        };
    }
}
