<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\StateConflict;
use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Cancelling records what happened during a round; before the start, walkers withdraw or the round is deleted.
 */
final class RoundNotStarted extends DomainException implements StateConflict
{
    public static function forRound(Uuid $roundId): self
    {
        return new self(\sprintf('Round %s has not started.', $roundId->toRfc4122()));
    }
}
