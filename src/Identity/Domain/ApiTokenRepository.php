<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\NotFound;
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
}
