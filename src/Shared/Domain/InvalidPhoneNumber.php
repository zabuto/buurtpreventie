<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

/**
 * A phone number with characters a walker cannot dial, or too few digits to reach anyone.
 */
final class InvalidPhoneNumber extends InvalidArgumentException implements InvalidValue
{
}
