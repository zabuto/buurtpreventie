<?php

declare(strict_types=1);

namespace App\Identity\UseCase\ListApiTokens;

final readonly class ListApiTokensOutput
{
    /**
     * @param list<array{id: string, name: string, scopes: list<string>, createdAt: string, expiresAt: string, lastUsedAt: string|null}> $tokens
     *     never the secret, which only the answer that created a token holds
     */
    public function __construct(
        public array $tokens,
    ) {
    }
}
