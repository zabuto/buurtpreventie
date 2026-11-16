<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Security;

use App\Audit\Domain\AuditPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * The audit log, which takes no change but wiping the name of an erased member, and the history of a round.
 */
final readonly class AuditVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['audit.erase_actor_name', 'audit.view_round_history'];

    public function __construct(
        private AuditPolicy $policy,
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

        return match ($attribute) {
            'audit.view_round_history' => $this->policy->mayViewRoundHistory($actor),
            default => $this->policy->mayEraseActorName($actor),
        };
    }
}
