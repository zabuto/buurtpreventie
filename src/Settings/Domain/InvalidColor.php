<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * A colour in any notation but `#rrggbb`.
 */
final class InvalidColor extends InvalidArgumentException implements InvalidValue
{
}
