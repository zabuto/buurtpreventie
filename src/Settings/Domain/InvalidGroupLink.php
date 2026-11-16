<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * A link that is not an invitation to a group in WhatsApp or Signal.
 */
final class InvalidGroupLink extends InvalidArgumentException implements InvalidValue
{
}
