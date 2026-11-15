<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

/**
 * Not one local part and one domain around a single @, or not valid UTF-8.
 */
final class InvalidEmailAddress extends InvalidArgumentException implements InvalidValue
{
}
