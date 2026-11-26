<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\InvalidValue;
use InvalidArgumentException;

/**
 * The password is common, holds the member's name or e-mail address, or is too simple. One refusal for all of them,
 * so trying passwords tells nobody which rule a guess broke.
 */
final class PasswordTooWeak extends InvalidArgumentException implements InvalidValue
{
}
