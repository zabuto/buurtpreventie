<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * A one-time login code of six digits. Digits only, so 0 and O or 1 and l never get mixed up and a phone offers
 * its number pad.
 */
final readonly class LoginCode
{
    /**
     * The code as typed or pasted, without the spaces and hyphens a member adds or copies along.
     */
    public static function typed(string $input): string
    {
        return (string) \preg_replace('/[\s\x{00A0}-]+/u', '', $input);
    }

    /**
     * The code in two groups of three, as the mail shows it.
     */
    public static function shown(string $code): string
    {
        return \substr($code, 0, 3) . ' ' . \substr($code, 3);
    }
}
