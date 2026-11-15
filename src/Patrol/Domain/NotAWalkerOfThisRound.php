<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DomainException;
use Symfony\Component\Uid\Uuid;

final class NotAWalkerOfThisRound extends DomainException
{
    public static function forMember(Uuid $roundId, Uuid $memberId): self
    {
        return new self(\sprintf('Member %s did not walk round %s.', $memberId->toRfc4122(), $roundId->toRfc4122()));
    }
}
