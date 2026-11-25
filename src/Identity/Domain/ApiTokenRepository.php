<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\NotFound;
use DateTimeImmutable;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

interface ApiTokenRepository
{
    /**
     * @throws NotFound
     */
    public function get(Uuid $id): ApiToken;

    /**
     * Stores the token when the running transaction commits.
     */
    public function add(#[SensitiveParameter] ApiToken $token): void;

    /**
     * @return list<ApiToken> revoked and expired tokens included
     */
    public function ofMember(Uuid $memberId): array;

    /**
     * @return list<ApiToken> of every member, neither revoked nor expired at this moment
     */
    public function usableAt(DateTimeImmutable $now): array;

    /**
     * The token whose secret has this SHA-256 hash; the secret itself is never stored or searched for.
     */
    public function bySecretHash(#[SensitiveParameter] string $secretHash): ?ApiToken;
}
