<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Security;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundComment;
use App\Patrol\Domain\RoundPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Comments on a round.
 */
final readonly class CommentVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['comment.add', 'comment.delete'];

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
        if (null === $actor) {
            return false;
        }

        return match ($attribute) {
            'comment.add' => $subject instanceof Round && $this->policy->mayComment($actor, $subject),
            default => $subject instanceof RoundComment && $this->policy->mayDeleteComment($actor, $subject),
        };
    }
}
