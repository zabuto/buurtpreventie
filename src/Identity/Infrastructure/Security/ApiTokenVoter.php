<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\CredentialPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Uid\Uuid;

/**
 * API tokens.
 */
final readonly class ApiTokenVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['api_token.create', 'api_token.revoke', 'api_token.revoke_all'];

    public function __construct(
        private CredentialPolicy $policy,
    ) {
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, strict: true);
    }

    #[Override]
    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    #[Override]
    public function vote(#[SensitiveParameter] TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        foreach ($attributes as $attribute) {
            if (\is_string($attribute) && $this->supportsAttribute($attribute)) {
                return $this->decide($attribute, $subject, $token) ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
            }
        }

        return self::ACCESS_ABSTAIN;
    }

    private function decide(string $attribute, mixed $subject, #[SensitiveParameter] TokenInterface $token): bool
    {
        $actor = TokenActor::of($token);
        if (null === $actor) {
            return false;
        }

        return match ($attribute) {
            'api_token.create' => $subject instanceof Uuid && $this->policy->mayCreateToken($actor, $subject),
            'api_token.revoke' => $subject instanceof ApiToken && $this->policy->mayRevokeToken($actor, $subject),
            default => $subject instanceof Uuid && $this->policy->mayRevokeAllTokens($actor, $subject),
        };
    }
}
