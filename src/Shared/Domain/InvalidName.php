<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

/**
 * Not valid UTF-8, or nothing but whitespace and punctuation: no key can tell such a name apart.
 */
final class InvalidName extends InvalidArgumentException implements InvalidValue
{
}
