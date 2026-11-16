<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface LoginCodeGenerator
{
    /**
     * 128 random bits in lower-case hex: the id a browser keeps for its login challenge.
     */
    public function newChallengeId(): string;

    /**
     * Six random digits.
     */
    public function newCode(): string;
}
