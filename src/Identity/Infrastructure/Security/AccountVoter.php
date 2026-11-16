<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\CredentialPolicy;
use App\Shared\Infrastructure\Security\TokenActor;
use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Uid\Uuid;

/**
 * The password of an account, invitations, and asking for a login code, which anyone may: nobody is logged in yet.
 */
final readonly class AccountVoter implements CacheableVoterInterface
{
    private const array ATTRIBUTES = ['account.set_password', 'account.request_login_code', 'account.issue_invitation'];

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
        if ('account.request_login_code' === $attribute) {
            return true;
        }

        $actor = TokenActor::of($token);
        if (null === $actor) {
            return false;
        }

        return match ($attribute) {
            'account.issue_invitation' => $this->policy->mayInvite($actor),
            default => $subject instanceof Uuid && $this->policy->maySetPassword($actor, $subject),
        };
    }
}
