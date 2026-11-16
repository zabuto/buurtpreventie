<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetReminderLeadTime;

final readonly class GetReminderLeadTimeOutput
{
    /**
     * @param int $daysAhead 1 to 7: the reminder goes out this many local calendar days before the round
     */
    public function __construct(
        public int $daysAhead,
    ) {
    }
}
