<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\StateConflict;
use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Without an active admin, nobody can manage members, catalogues or settings, nor appoint a new admin.
 */
final class LastAdmin extends DomainException implements StateConflict
{
    public static function forMember(Uuid $memberId): self
    {
        return new self(\sprintf('Member %s is the last active admin.', $memberId->toRfc4122()));
    }
}
