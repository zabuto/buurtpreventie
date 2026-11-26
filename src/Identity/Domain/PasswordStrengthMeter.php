<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use SensitiveParameter;

/**
 * A rough estimate of how hard a password is to guess, the safety net under the list of common passwords.
 */
interface PasswordStrengthMeter
{
    public function isStrongEnough(#[SensitiveParameter] string $password): bool;
}
