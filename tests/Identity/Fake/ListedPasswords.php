<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\CommonPasswords;
use Override;
use SensitiveParameter;

final readonly class ListedPasswords implements CommonPasswords
{
    /**
     * @param list<string> $passwords in lower case and without whitespace, as the real list holds them
     */
    public function __construct(
        private array $passwords = [],
    ) {
    }

    #[Override]
    public function contains(#[SensitiveParameter] string $normalised): bool
    {
        return \in_array($normalised, $this->passwords, strict: true);
    }
}
