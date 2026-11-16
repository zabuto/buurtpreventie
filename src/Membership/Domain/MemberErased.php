<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\StateConflict;
use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * An erased member stays erased: taking new details would bring personal data back.
 */
final class MemberErased extends DomainException implements StateConflict
{
    public static function forMember(Uuid $memberId): self
    {
        return new self(\sprintf('Member %s is erased.', $memberId->toRfc4122()));
    }
}
