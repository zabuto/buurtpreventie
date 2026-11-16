<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use SensitiveParameter;

/**
 * A keyed hash over a challenge id and a code. Without the key, a copy of the database cannot try the million codes.
 */
interface LoginCodeHasher
{
    public function hash(string $challengeId, #[SensitiveParameter] string $code): string;
}
