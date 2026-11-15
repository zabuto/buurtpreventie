<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Only a walked round has something to report: one that has not started, or went without a buddy, has not.
 */
final class RoundNotWalked extends DomainException
{
    public static function forRound(Uuid $roundId, RoundStatus $status): self
    {
        return new self(\sprintf('Round %s is %s, not walked.', $roundId->toRfc4122(), $status->value));
    }
}
