<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface SecretGenerator
{
    /**
     * A new secret that nobody can guess, to hand out once.
     */
    public function newSecret(): string;
}
