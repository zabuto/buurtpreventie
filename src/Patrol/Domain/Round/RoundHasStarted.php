<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\StateConflict;
use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Who walked a round is history from its start: nobody signs up or withdraws after it.
 */
final class RoundHasStarted extends DomainException implements StateConflict
{
    public static function forRound(Uuid $roundId): self
    {
        return new self(\sprintf('Round %s has started.', $roundId->toRfc4122()));
    }
}
