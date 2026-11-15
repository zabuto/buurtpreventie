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
use Symfony\Component\Uid\Uuid;

/**
 * Who walks: signing up and withdrawing, for oneself or on behalf of another member.
 */
final readonly class ParticipationVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['participation.sign_up', 'participation.withdraw', 'participation.withdraw_member_from_upcoming'];

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
                return $this->decide($attribute, $subject, $token) ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
            }
        }

        return self::ACCESS_ABSTAIN;
    }

    private function decide(string $attribute, mixed $subject, #[SensitiveParameter] TokenInterface $token): bool
    {
        $actor = TokenActor::of($token);
        if (null === $actor || !$subject instanceof Uuid) {
            return false;
        }

        return match ($attribute) {
            'participation.sign_up' => $this->policy->maySignUp($actor, $subject),
            'participation.withdraw' => $this->policy->mayWithdraw($actor, $subject),
            default => $this->policy->mayWithdrawFromUpcomingRounds($actor),
        };
    }
}
