<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * Passwords and API tokens belong to the member alone. An admin sees every active token, to spot one that does not
 * belong, and revokes every token of a member, such as when deactivating or erasing them. A coordinator or admin has an
 * invitation sent; only an admin sees its link, to hand it on to a new member without mail.
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

    public function mayListAllTokens(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }

    public function maySetPassword(Actor $actor, Uuid $memberId): bool
    {
        return $actor->is($memberId);
    }

    public function mayInvite(Actor $actor): bool
    {
        return $actor->hasRole(Role::Coordinator) || $actor->hasRole(Role::Admin);
    }

    public function maySeeInvitationLink(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }
}
