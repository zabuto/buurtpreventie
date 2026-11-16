<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;

/**
 * What a token is called, what it may do and until when.
 */
final readonly class TokenTerms
{
    public string $name;

    /** @var list<TokenScope> */
    public array $scopes;

    /**
     * @param list<TokenScope> $scopes
     */
    public function __construct(
        string $name,
        array $scopes,
        public DateTimeImmutable $expiresAt,
    ) {
        $this->name = \trim($name);
        $this->scopes = \array_values(\array_unique($scopes, \SORT_REGULAR));
    }
}
