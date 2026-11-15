<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

/**
 * Whether a round goes ahead. Derived, never stored: a full round is staffed, and "full" is the number of open spots.
 */
enum RoundStatus: string
{
    case SeekingBuddy = 'seeking_buddy';
    case Staffed = 'staffed';
    case Walked = 'walked';
    case NotWalked = 'not_walked';
}
