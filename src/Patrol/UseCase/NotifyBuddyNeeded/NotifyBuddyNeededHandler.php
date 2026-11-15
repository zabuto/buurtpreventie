<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\NotifyBuddyNeeded;

use App\Patrol\Domain\Round\BuddyCaller;
use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;

/**
 * Sends the buddy call of every round that has not started and whose call is due. Each round remembers its call, so
 * it goes out once.
 */
#[WriteAction(authorization: 'round.notify_buddy_needed', audit: 'round.buddy_call_sent')]
final readonly class NotifyBuddyNeededHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private BuddyCaller $buddyCaller,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(NotifyBuddyNeeded $input): void
    {
        $this->authorization->denyUnlessGranted('round.notify_buddy_needed');

        $this->transaction->run(function (): void {
            $now = $this->clock->now();
            foreach ($this->rounds->upcoming($now) as $round) {
                $recipients = $this->buddyCaller->callIfDue($round, $now);
                if (null === $recipients) {
                    continue;
                }

                $this->auditTrail->record(new AuditedAction('round.buddy_call_sent', 'round', $round->id(), [
                    'recipients' => ['old' => null, 'new' => $recipients],
                ]));
            }
        });
    }
}
