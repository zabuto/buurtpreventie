<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * A round has one report; the existing one has to go before another can take its place.
 */
final class RoundAlreadyReported extends DomainException
{
    public static function forRound(Uuid $roundId): self
    {
        return new self(\sprintf('Round %s already has a report.', $roundId->toRfc4122()));
    }
}
