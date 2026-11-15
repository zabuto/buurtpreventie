<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * A phone number with characters a walker cannot dial, or too few digits to reach anyone.
 */
final class InvalidPhoneNumber extends InvalidArgumentException implements InvalidValue
{
}
