<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\BackedEnumValues;

/**
 * Which rounds of a walker a list shows: the ones still to walk, the walked ones that wait for a report, or all.
 */
enum WalkerRoundView: string
{
    use BackedEnumValues;

    case Upcoming = 'upcoming';
    case ToReport = 'to_report';
    case All = 'all';
}
