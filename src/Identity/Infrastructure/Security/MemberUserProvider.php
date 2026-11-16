<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\AccountRepository;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberIdentity;
use App\Shared\Infrastructure\Security\MemberUser;
use Override;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Reloads the member of a session on every request: a deactivated or erased member drops out of the session at once,
 * and a changed role counts from the next request. Every member it hands out carries the version of their
 * credentials, the moment of their last new password, which the "stay logged in" cookie is signed with.
 *
 * @implements UserProviderInterface<MemberUser>
 */
final readonly class MemberUserProvider implements UserProviderInterface
{
    public function __construct(
        private MemberDirectory $members,
        private AccountRepository $accounts,
    ) {
    }

    #[Override]
    public function refreshUser(UserInterface $user): MemberUser
    {
        if (!$user instanceof MemberUser) {
            throw new UnsupportedUserException(\sprintf('Only a %s is refreshed here.', MemberUser::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return MemberUser::class === $class;
    }

    /**
     * @throws UserNotFoundException for an unknown, deactivated or erased member
     */
    #[Override]
    public function loadUserByIdentifier(string $identifier): MemberUser
    {
        $identity = Uuid::isValid($identifier) ? $this->members->identity(Uuid::fromString($identifier)) : null;
        if (null === $identity || !$identity->active) {
            throw new UserNotFoundException('No active member has this id.');
        }

        return $this->userOf($identity);
    }

    public function userOf(MemberIdentity $identity): MemberUser
    {
        $changedAt = $this->accounts->ofMember($identity->memberId)?->credentialsChangedAt();

        return new MemberUser($identity->memberId, $identity->roles, $changedAt?->format('U.u') ?? '');
    }
}
