<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\ApiTokenRepository;
use App\Shared\Domain\NotFound;
use DateTimeImmutable;
use Override;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final class InMemoryApiTokenRepository implements ApiTokenRepository
{
    /** @var array<string, ApiToken> */
    private array $tokens = [];

    #[Override]
    public function get(Uuid $id): ApiToken
    {
        return $this->tokens[$id->toRfc4122()] ?? throw NotFound::forId('API token', $id);
    }

    #[Override]
    public function add(#[SensitiveParameter] ApiToken $token): void
    {
        $this->tokens[$token->id()->toRfc4122()] = $token;
    }

    #[Override]
    public function ofMember(Uuid $memberId): array
    {
        return \array_values(\array_filter($this->tokens, static fn(#[SensitiveParameter] ApiToken $token): bool => $token->memberId()->equals($memberId)));
    }

    #[Override]
    public function usableAt(DateTimeImmutable $now): array
    {
        return \array_values(\array_filter($this->tokens, static fn(#[SensitiveParameter] ApiToken $token): bool => $token->isUsableAt($now)));
    }

    #[Override]
    public function bySecretHash(#[SensitiveParameter] string $secretHash): ?ApiToken
    {
        foreach ($this->tokens as $token) {
            if (\hash_equals($token->secretHash(), $secretHash)) {
                return $token;
            }
        }

        return null;
    }
}
