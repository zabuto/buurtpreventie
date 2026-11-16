<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\AccountRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Transaction;
use App\Shared\Infrastructure\Security\RunAs;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Uid\Uuid;

/**
 * Records a login and a failed attempt on a known member in the audit log, with the way the member tried and the IP
 * address shortened (the last byte of IPv4, the last 80 bits of IPv6): enough to spot a pattern, not to trace a
 * person.
 */
final readonly class LoginRecorder
{
    public function __construct(
        private AccountRepository $accounts,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private RunAs $runAs,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Runs once the member is in the token storage, so the entry names the member as actor.
     */
    public function loggedIn(Uuid $memberId, ?string $ip, LoginMethod $method): void
    {
        $this->transaction->run(function () use ($memberId, $ip, $method): void {
            $this->accounts->ofMember($memberId)?->recordLogin($this->clock->now());
            $this->auditTrail->record(self::action('account.logged_in', $memberId, $ip, $method));
        });
    }

    /**
     * Nobody proved who made the attempt, so the entry names no actor.
     */
    public function failed(Uuid $memberId, ?string $ip, LoginMethod $method): void
    {
        $this->runAs->system(Channel::Web, fn() => $this->transaction->run(function () use ($memberId, $ip, $method): void {
            $this->auditTrail->record(self::action('account.login_failed', $memberId, $ip, $method));
        }));
    }

    private static function action(string $action, Uuid $memberId, ?string $ip, LoginMethod $method): AuditedAction
    {
        return new AuditedAction($action, 'member', $memberId, [
            'ip' => ['old' => null, 'new' => null === $ip ? null : IpUtils::anonymize($ip, 1, 10)],
            'method' => ['old' => null, 'new' => $method->value],
        ]);
    }
}
