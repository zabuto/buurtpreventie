<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Security;

use App\Patrol\Domain\Round\RoundPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * The meeting point and outcome catalogues.
 */
final readonly class CatalogueVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['catalogue.manage'];

    public function __construct(
        private RoundPolicy $policy,
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
                return $this->decide($token) ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
            }
        }

        return self::ACCESS_ABSTAIN;
    }

    private function decide(#[SensitiveParameter] TokenInterface $token): bool
    {
        $actor = TokenActor::of($token);

        return null !== $actor && $this->policy->mayManageCatalogue($actor);
    }
}
