<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * A round is planned ahead: one that starts at or before this moment could never take walkers.
 */
final class StartInPast extends DomainException
{
    public static function at(DateTimeImmutable $startsAt): self
    {
        return new self(\sprintf('A round cannot start in the past, as at %s.', $startsAt->format(\DATE_ATOM)));
    }
}
