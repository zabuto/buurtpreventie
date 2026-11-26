<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use App\Identity\Domain\Account;
use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\CurrentSession;
use App\Identity\Domain\GuessablePasswords;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\PasswordTooWeak;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\ReauthenticationRequired;
use App\Shared\Domain\RecentAuthentication;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Sets a member's password, opening the account on first use. Every other session and every device that stays
 * logged in is logged out; the session that set it stays. It takes a recent login instead of the current password:
 * POST /auth/confirm checks that behind the login throttling, and a login with a code counts too, which is how a
 * member replaces a forgotten password. A password that is easy to guess is refused, also on a change.
 */
#[WriteAction(authorization: 'account.set_password', audit: 'account.password_changed')]
final readonly class SetPasswordHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private PasswordHasher $passwordHasher,
        private Authorization $authorization,
        private ExecutionContext $executionContext,
        private RecentAuthentication $recentAuthentication,
        private MemberDirectory $members,
        private GuessablePasswords $guessablePasswords,
        private CurrentSession $currentSession,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AccessDenied for another member, or nobody acting
     * @throws ReauthenticationRequired without a recent login
     * @throws PasswordTooWeak for a common password, one with the member's name or e-mail address, or a too simple one
     */
    public function __invoke(SetPassword $input): void
    {
        $memberId = null === $input->memberId ? $this->executionContext->actor()?->memberId : Uuid::fromString($input->memberId);
        if (null === $memberId) {
            throw AccessDenied::forAction('account.set_password');
        }

        $this->authorization->denyUnlessGranted('account.set_password', $memberId);
        if (!$this->recentAuthentication->isRecent()) {
            throw ReauthenticationRequired::forAction('account.set_password');
        }
        if ($this->guessablePasswords->isGuessable($input->password, $this->personalDetails($memberId))) {
            throw new PasswordTooWeak(\sprintf('Member %s chose a password that is easy to guess.', $memberId->toRfc4122()));
        }
        $hash = $this->passwordHasher->hash($input->password);

        $this->transaction->run(function () use ($memberId, $hash): void {
            $account = $this->accounts->ofMember($memberId);
            if (null === $account) {
                $account = Account::open($memberId);
                $this->accounts->add($account);
            }

            $account->changePassword($hash, $this->clock->now());
            $this->auditTrail->record(new AuditedAction('account.password_changed', 'member', $memberId));
        });

        $this->currentSession->keepAfterNewPassword($memberId);
    }

    /**
     * @return list<string>
     */
    private function personalDetails(Uuid $memberId): array
    {
        $profile = $this->members->profilesOf([$memberId])[$memberId->toRfc4122()] ?? null;

        return \array_values(\array_filter(
            [$profile?->displayName, $profile?->name, $this->members->emailOf($memberId)?->value],
            static fn(?string $detail): bool => null !== $detail,
        ));
    }
}
