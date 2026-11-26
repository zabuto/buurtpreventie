<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Password;

use App\Identity\Domain\PasswordStrengthMeter;
use Override;
use SensitiveParameter;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\PasswordStrengthValidator;

/**
 * Symfony's PasswordStrength estimate, from medium up: "fiets lamp regen" passes, a word with digits does not.
 */
final readonly class SymfonyPasswordStrengthMeter implements PasswordStrengthMeter
{
    #[Override]
    public function isStrongEnough(#[SensitiveParameter] string $password): bool
    {
        return PasswordStrengthValidator::estimateStrength($password) >= PasswordStrength::STRENGTH_MEDIUM;
    }
}
