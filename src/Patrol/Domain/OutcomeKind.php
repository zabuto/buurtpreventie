<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

enum OutcomeKind: string
{
    case NothingToReport = 'nothing_to_report';
    case Remarks = 'remarks';
    case Incident = 'incident';

    /**
     * A note only makes sense next to something to report; with nothing to report it would contradict the outcome.
     */
    public function keepsNote(): bool
    {
        return self::NothingToReport !== $this;
    }
}
