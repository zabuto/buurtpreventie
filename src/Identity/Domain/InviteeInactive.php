<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\StateConflict;
use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * A deactivated or erased member cannot log in, so an invitation would lead nowhere.
 */
final class InviteeInactive extends DomainException implements StateConflict
{
    public static function forMember(Uuid $memberId): self
    {
        return new self(\sprintf('Member %s is not active.', $memberId->toRfc4122()));
    }
}
