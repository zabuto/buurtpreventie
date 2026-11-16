<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Security;

use App\Settings\Domain\SettingsPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Reading every setting with its value and changing them.
 */
final readonly class SettingsVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['settings.view', 'settings.change'];

    public function __construct(
        private SettingsPolicy $policy,
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
                return $this->decide($attribute, $token) ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
            }
        }

        return self::ACCESS_ABSTAIN;
    }

    private function decide(string $attribute, #[SensitiveParameter] TokenInterface $token): bool
    {
        $actor = TokenActor::of($token);
        if (null === $actor) {
            return false;
        }

        return 'settings.view' === $attribute ? $this->policy->mayView($actor) : $this->policy->mayChange($actor);
    }
}
