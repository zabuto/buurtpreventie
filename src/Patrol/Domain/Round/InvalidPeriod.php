<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * A list of rounds covers a period that ends after its start and stays within a quarter, so one request never
 * reads the whole history.
 */
final class InvalidPeriod extends InvalidArgumentException implements InvalidValue
{
}
