<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\RecentAuthentication;
use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Reads the moment of the last proof from the token, where the browser authenticators put it at login and at
 * confirmation. The token of "stay logged in" and of an API token never carries it.
 */
final readonly class TokenRecentAuthentication implements RecentAuthentication
{
    public const string AUTHENTICATED_AT = 'authenticated_at';
    private const int RECENT_IN_SECONDS = 15 * 60;

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ClockInterface $clock,
    ) {
    }

    public static function stamp(#[SensitiveParameter] TokenInterface $token, DateTimeImmutable $now): void
    {
        $token->setAttribute(self::AUTHENTICATED_AT, $now);
    }

    #[Override]
    public function isRecent(): bool
    {
        $token = $this->tokenStorage->getToken();
        $at = null !== $token && $token->hasAttribute(self::AUTHENTICATED_AT) ? $token->getAttribute(self::AUTHENTICATED_AT) : null;

        return $at instanceof DateTimeImmutable && ($this->clock->now()->getTimestamp() - $at->getTimestamp()) <= self::RECENT_IN_SECONDS;
    }
}
