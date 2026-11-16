<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\StateConflict;
use DomainException;

/**
 * Display names are unique on their lookup key, so "Jan H." and "jan h" collide, as do "José" and "Jose".
 */
final class DisplayNameTaken extends DomainException implements StateConflict
{
    public static function forName(string $displayName): self
    {
        return new self(\sprintf('Another member has a display name like "%s".', $displayName));
    }
}
