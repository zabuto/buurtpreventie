<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * A round needs at least one walker and allows at least as many as it needs.
 */
final class InvalidStaffingRule extends InvalidArgumentException implements InvalidValue
{
}
