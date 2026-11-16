<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use SensitiveParameter;

interface PasswordHasher
{
    public function hash(#[SensitiveParameter] string $password): string;

    public function verify(string $hash, #[SensitiveParameter] string $password): bool;

    /**
     * Whether the hash was made with an older algorithm or cost than a new one gets, such as a hash the legacy app made.
     */
    public function needsRehash(string $hash): bool;
}
