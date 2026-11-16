<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\StateConflict;
use DomainException;

/**
 * The association switched API tokens off, so a new token would let nobody in.
 */
final class ApiTokensDisabled extends DomainException implements StateConflict
{
    public static function create(): self
    {
        return new self('API tokens are switched off.');
    }
}
