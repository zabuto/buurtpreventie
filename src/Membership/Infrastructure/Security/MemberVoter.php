<?php

declare(strict_types=1);

namespace App\Membership\Infrastructure\Security;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Members: the list every member reads, managing members, the choices each member makes for themselves, and the
 * first admin, which only the system adds.
 */
final readonly class MemberVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = [
        'member.add_first_admin',
        'member.add',
        'member.change',
        'member.change_roles',
        'member.deactivate',
        'member.reactivate',
        'member.erase',
        'member.invite',
        'member.change_consent',
        'member.change_open_round_notifications',
        'member.list',
    ];

    public function __construct(
        private MemberPolicy $policy,
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
        if ('member.add_first_admin' === $attribute) {
            return TokenActor::isSystem($token);
        }
        if ('member.list' === $attribute) {
            return null !== TokenActor::of($token) || TokenActor::isSystem($token);
        }

        $actor = TokenActor::of($token);
        if (null === $actor) {
            return false;
        }

        return match ($attribute) {
            'member.change_consent', 'member.change_open_round_notifications' => $subject instanceof Member
                && $this->policy->mayChangeOwnChoice($actor, $subject),
            'member.invite' => $this->policy->mayInvite($actor),
            default => $this->policy->mayManage($actor),
        };
    }
}
