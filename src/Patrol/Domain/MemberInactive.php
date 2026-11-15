<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Only an active member walks: a deactivated or erased one gets no reminders and cannot be reached.
 */
final class MemberInactive extends DomainException
{
    public static function forMember(Uuid $memberId): self
    {
        return new self(\sprintf('Member %s is not active.', $memberId->toRfc4122()));
    }
}
