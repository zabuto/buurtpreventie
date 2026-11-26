<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\PasswordStrengthMeter;
use Override;
use SensitiveParameter;

/**
 * Finds every password strong enough, except the ones named weak.
 */
final readonly class FixedStrengthMeter implements PasswordStrengthMeter
{
    /**
     * @param list<string> $weak
     */
    public function __construct(
        private array $weak = [],
    ) {
    }

    #[Override]
    public function isStrongEnough(#[SensitiveParameter] string $password): bool
    {
        return !\in_array($password, $this->weak, strict: true);
    }
}
