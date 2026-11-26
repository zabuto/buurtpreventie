<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use SensitiveParameter;

/**
 * Passwords so widely used that guessing starts with them, kept with the code: no password leaves the server.
 */
interface CommonPasswords
{
    /**
     * @param string $normalised in lower case and without whitespace
     */
    public function contains(#[SensitiveParameter] string $normalised): bool;
}
