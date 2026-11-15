<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

enum Withdrawal
{
    case Withdrawn;
    /** The round seeks a buddy again: the walkers who remain hear that their round may not go ahead. */
    case DroppedBelowMinimum;
    /** The round has no walkers left and goes: the handler deletes it. */
    case LastWalkerLeft;
    /** Nothing changed: the member was not signed up, so withdrawing twice is harmless. */
    case NotSignedUp;
}
