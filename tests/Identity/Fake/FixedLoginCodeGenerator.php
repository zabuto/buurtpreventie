<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\LoginCodeGenerator;
use Override;

/**
 * Hands out the given challenge ids in turn and always the same code, so a test knows what the member received.
 */
final class FixedLoginCodeGenerator implements LoginCodeGenerator
{
    public const string CODE = '482913';
    public const string FIRST_CHALLENGE = '0123456789abcdef0123456789abcdef';
    public const string SECOND_CHALLENGE = 'fedcba9876543210fedcba9876543210';

    private int $issued = 0;

    #[Override]
    public function newChallengeId(): string
    {
        return 0 === $this->issued++ ? self::FIRST_CHALLENGE : self::SECOND_CHALLENGE;
    }

    #[Override]
    public function newCode(): string
    {
        return self::CODE;
    }
}
