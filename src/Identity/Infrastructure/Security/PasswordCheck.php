<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\PasswordHasher;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\InvalidEmailAddress;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Transaction;
use App\Shared\Infrastructure\Security\MemberUser;
use SensitiveParameter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The two steps of a password login, run by the passport of PasswordAuthenticator: find the active member of an
 * address, then check the password. An unknown address or inactive member costs one hash too, so the timing of a
 * refusal does not tell whether an address belongs to a member.
 */
final readonly class PasswordCheck
{
    public function __construct(
        private MemberDirectory $members,
        private AccountRepository $accounts,
        private PasswordHasher $passwordHasher,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws LoginRefused for an unknown address or an inactive member, after a hash that costs what a check costs
     */
    public function activeMember(string $email, #[SensitiveParameter] string $password): MemberUser
    {
        try {
            $identity = $this->members->identify(EmailAddress::fromString($email));
        } catch (InvalidEmailAddress) {
            $identity = null;
        }

        if (null === $identity || !$identity->active) {
            $this->passwordHasher->hash($password);

            throw new LoginRefused(null === $identity ? 'unknown_address' : 'member_inactive', $identity?->memberId);
        }

        return new MemberUser($identity->memberId, $identity->roles);
    }

    /**
     * Renews a hash made with an older algorithm or cost on the spot, while the password is at hand.
     *
     * @throws LoginRefused for a member without a password or a wrong password
     */
    public function password(UserInterface $user, #[SensitiveParameter] mixed $password): bool
    {
        \assert($user instanceof MemberUser && \is_string($password), 'The badges of this authenticator hold a member and a text.');
        $account = $this->accounts->ofMember($user->memberId);
        $hash = $account?->passwordHash();
        if (null === $account || null === $hash) {
            $this->passwordHasher->hash($password);

            throw new LoginRefused('no_password', $user->memberId);
        }

        if (!$this->passwordHasher->verify($hash, $password)) {
            throw new LoginRefused('wrong_password', $user->memberId);
        }

        if ($this->passwordHasher->needsRehash($hash)) {
            $renewed = $this->passwordHasher->hash($password);
            $this->transaction->run(static fn() => $account->renewPasswordHash($renewed));
        }

        return true;
    }
}
