<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\BackedEnumValues;

/**
 * Whether a round goes ahead. Derived, never stored: a full round is staffed, and "full" is the number of open spots.
 * A cancelled round started but was not walked, such as after a no-show or force majeure.
 */
enum RoundStatus: string
{
    use BackedEnumValues;

    case SeekingBuddy = 'seeking_buddy';
    case Staffed = 'staffed';
    case Walked = 'walked';
    case NotWalked = 'not_walked';
    case Cancelled = 'cancelled';

    public static function of(bool $cancelled, bool $started, bool $staffed): self
    {
        return match (true) {
            $cancelled => self::Cancelled,
            $started => $staffed ? self::Walked : self::NotWalked,
            default => $staffed ? self::Staffed : self::SeekingBuddy,
        };
    }
}
