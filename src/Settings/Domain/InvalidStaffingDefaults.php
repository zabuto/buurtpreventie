<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * New rounds need at least one walker and allow at least as many as they need.
 */
final class InvalidStaffingDefaults extends InvalidArgumentException implements InvalidValue
{
}
