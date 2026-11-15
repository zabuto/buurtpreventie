<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DomainException;
use Symfony\Component\Uid\Uuid;

final class RoundIsFull extends DomainException
{
    public static function forRound(Uuid $roundId): self
    {
        return new self(\sprintf('Round %s has no open spots.', $roundId->toRfc4122()));
    }
}
