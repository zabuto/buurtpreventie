<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Who walked a round is history from its start: nobody signs up or withdraws after it.
 */
final class RoundHasStarted extends DomainException
{
    public static function forRound(Uuid $roundId): self
    {
        return new self(\sprintf('Round %s has started.', $roundId->toRfc4122()));
    }
}
