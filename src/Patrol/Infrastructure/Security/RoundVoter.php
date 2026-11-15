<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Security;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Uid\Uuid;

/**
 * Rounds themselves: planning, changing, cancelling, reading, and the scheduled messages only the system sends.
 */
final readonly class RoundVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = [
        'round.create',
        'round.change',
        'round.delete',
        'round.cancel',
        'round.list',
        'round.view',
        'round.send_reminders',
        'round.notify_buddy_needed',
    ];

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
        if (\in_array($attribute, ['round.send_reminders', 'round.notify_buddy_needed'], strict: true)) {
            return TokenActor::isSystem($token);
        }

        if (\in_array($attribute, ['round.list', 'round.view'], strict: true)) {
            return null !== TokenActor::of($token) || TokenActor::isSystem($token);
        }

        $actor = TokenActor::of($token);
        if (null === $actor) {
            return false;
        }

        return match ($attribute) {
            'round.create' => $subject instanceof Uuid && $this->policy->mayCreate($actor, $subject),
            'round.cancel' => $subject instanceof Round && $this->policy->mayCancel($actor, $subject),
            default => $subject instanceof Round && $this->policy->mayChange($actor, $subject),
        };
    }
}
