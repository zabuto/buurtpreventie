<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * Half a position, or one off the globe: a meeting point has a whole position or none.
 */
final class InvalidCoordinates extends InvalidArgumentException implements InvalidValue
{
}
