<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mcp;

/**
 * The language of the association by its English name, for the instruction in a prompt.
 */
final readonly class LanguageName
{
    public static function of(string $language): string
    {
        return match ($language) {
            'en' => 'English',
            default => 'Dutch',
        };
    }
}
