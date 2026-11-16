<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use App\Identity\Domain\Account;
use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\CurrentSession;
use App\Identity\Domain\PasswordHasher;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
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
 * member replaces a forgotten password.
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
        private CurrentSession $currentSession,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AccessDenied for another member, or nobody acting
     * @throws ReauthenticationRequired without a recent login
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
}
