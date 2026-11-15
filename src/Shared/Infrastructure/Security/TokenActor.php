<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Reads who acts from a token. The execution context and every voter read it here, from the same token, so a handler
 * and the voters it asks never disagree on the actor.
 */
final readonly class TokenActor
{
    public static function of(#[SensitiveParameter] ?TokenInterface $token): ?Actor
    {
        $user = $token?->getUser();

        return $user instanceof MemberUser ? $user->actor() : null;
    }

    public static function isSystem(#[SensitiveParameter] ?TokenInterface $token): bool
    {
        return $token?->getUser() instanceof SystemUser;
    }
}
