<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\LoginCodeGenerator;
use Override;

final readonly class RandomLoginCodeGenerator implements LoginCodeGenerator
{
    #[Override]
    public function newChallengeId(): string
    {
        return \bin2hex(\random_bytes(16));
    }

    #[Override]
    public function newCode(): string
    {
        return \sprintf('%06d', \random_int(0, 999_999));
    }
}
