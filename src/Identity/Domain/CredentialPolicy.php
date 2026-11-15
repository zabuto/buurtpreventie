<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * Passwords and API tokens belong to the member alone. An admin only revokes every token of a member, as part of
 * deactivating or erasing them.
 */
final readonly class CredentialPolicy
{
    public function mayCreateToken(Actor $actor, Uuid $memberId): bool
    {
        return $actor->is($memberId);
    }

    public function mayRevokeToken(Actor $actor, #[SensitiveParameter] ApiToken $apiToken): bool
    {
        return $actor->is($apiToken->memberId());
    }

    public function mayRevokeAllTokens(Actor $actor, Uuid $memberId): bool
    {
        return $actor->is($memberId) || $actor->hasRole(Role::Admin);
    }

    public function maySetPassword(Actor $actor, Uuid $memberId): bool
    {
        return $actor->is($memberId);
    }
}
