<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Password;

use App\Identity\Domain\CommonPasswords;
use LogicException;
use Override;
use SensitiveParameter;

/**
 * The list in common-passwords.txt next to this class, read on each check: only setting a password asks.
 */
final readonly class CommonPasswordList implements CommonPasswords
{
    private const string FILE = __DIR__ . '/common-passwords.txt';

    /**
     * @throws LogicException when the file is missing, which would let every common password through unnoticed
     */
    #[Override]
    public function contains(#[SensitiveParameter] string $normalised): bool
    {
        $lines = \file(self::FILE, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $lines) {
            throw new LogicException(\sprintf('The list of common passwords is missing at %s.', self::FILE));
        }

        return !\str_starts_with($normalised, '#') && \in_array($normalised, $lines, strict: true);
    }
}
