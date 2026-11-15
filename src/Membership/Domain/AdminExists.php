<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\StateConflict;
use DomainException;

/**
 * Once an active admin exists, admins appoint each other; the system no longer adds one.
 */
final class AdminExists extends DomainException implements StateConflict
{
    public static function create(): self
    {
        return new self('An active admin exists; an admin adds the next one.');
    }
}
