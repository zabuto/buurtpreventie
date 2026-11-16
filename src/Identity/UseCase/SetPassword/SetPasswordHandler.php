<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use App\Identity\Domain\Account;
use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\PasswordHasher;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Sets a member's password, opening the account on first use. Every device that stays logged in is logged out.
 */
#[WriteAction(authorization: 'account.set_password', audit: 'account.password_changed')]
final readonly class SetPasswordHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private PasswordHasher $passwordHasher,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetPassword $input): void
    {
        $memberId = Uuid::fromString($input->memberId);
        $this->authorization->denyUnlessGranted('account.set_password', $memberId);
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
    }
}
