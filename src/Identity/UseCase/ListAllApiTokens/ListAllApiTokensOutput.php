<?php

declare(strict_types=1);

namespace App\Identity\UseCase\ListAllApiTokens;

final readonly class ListAllApiTokensOutput
{
    /**
     * @param list<array{id: string, memberId: string, member: string|null, name: string, scopes: list<string>, createdAt: string, expiresAt: string, lastUsedAt: string|null}> $tokens
     *     member is the display name, null for an erased member; never the secret or its hash
     */
    public function __construct(
        public array $tokens,
    ) {
    }
}
