<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * A comment without text tells the other walkers nothing.
 */
final class EmptyComment extends InvalidArgumentException implements InvalidValue
{
}
