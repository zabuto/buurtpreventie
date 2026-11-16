<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * A token expires after it is issued and within 365 days, so a leaked token that nobody revokes stops on its own.
 */
final class InvalidTokenExpiry extends InvalidArgumentException implements InvalidValue
{
}
