<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\SecretGenerator;
use Override;

/**
 * 32 random bytes, hex-encoded behind a prefix (bpt_ for a token, bpi_ for an invitation) that tells a leaked secret apart in logs and secret scanners.
 */
final readonly class RandomSecretGenerator implements SecretGenerator
{
    #[Override]
    public function newSecret(): string
    {
        return 'bpt_' . \bin2hex(\random_bytes(32));
    }

    #[Override]
    public function newInvitationSecret(): string
    {
        return 'bpi_' . \bin2hex(\random_bytes(32));
    }
}
