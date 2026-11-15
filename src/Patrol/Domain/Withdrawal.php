<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

enum Withdrawal
{
    case Withdrawn;
    /** The round has no walkers left and goes: the handler deletes it. */
    case LastWalkerLeft;
    /** Nothing changed: the member was not signed up, so withdrawing twice is harmless. */
    case NotSignedUp;
}
