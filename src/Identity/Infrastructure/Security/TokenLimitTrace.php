<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\ApiTokenRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use DateTimeInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Makes a token that reaches its daily limit visible, the first time on each local day: an audit entry with the
 * name of the token and the channel of the request, and a mail to its member, who can tell whether it was them. A
 * system event, not a change by a member, so no voter asks. Reading itself stays out of the audit log: that would
 * turn the log into a reading history of every member.
 */
final readonly class TokenLimitTrace
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private AuditTrail $auditTrail,
        private Notifier $notifier,
        private Transaction $transaction,
        private ClockInterface $clock,
        #[Autowire(service: 'cache.token_requests')]
        private CacheItemPoolInterface $traced,
    ) {
    }

    public function dailyLimitReached(string $tokenId): void
    {
        $now = $this->clock->now();
        $marker = $this->traced->getItem('limit_reached.' . $tokenId . '.' . LocalTime::of($now)->format('Y-m-d'));
        if ($marker->isHit()) {
            return;
        }

        $token = $this->tokens->get(Uuid::fromString($tokenId));
        $this->transaction->run(function () use ($token, $now): void {
            $this->auditTrail->record(new AuditedAction('api_token.limit_reached', 'api_token', $token->id(), [
                'name' => ['old' => null, 'new' => $token->name()],
            ]));
            $this->notifier->send(new Notification($token->memberId(), 'account.token_limit_reached', [
                'tokenName' => $token->name(),
                'reachedAt' => $now->format(DateTimeInterface::ATOM),
            ]));
        });

        $this->traced->save($marker->set(true)->expiresAfter(2 * 86_400));
    }
}
