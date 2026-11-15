<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\Withdrawal;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Takes a member who stops walking, deactivated or erased, out of every round that has not started. A round left
 * without walkers goes; a round that drops below its minimum tells the walkers who stay, so they can find a buddy.
 */
#[WriteAction(
    authorization: 'participation.withdraw_member_from_upcoming',
    audit: 'participation.withdrawn_automatically',
)]
final readonly class WithdrawMemberFromUpcomingRoundsHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Notifier $notifier,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(WithdrawMemberFromUpcomingRounds $input): void
    {
        $memberId = Uuid::fromString($input->memberId);
        $this->authorization->denyUnlessGranted('participation.withdraw_member_from_upcoming', $memberId);

        $this->transaction->run(function () use ($memberId): void {
            $now = $this->clock->now();
            foreach ($this->rounds->upcomingWithWalker($memberId, $now) as $round) {
                $this->withdraw($round, $memberId, $now);
            }
        });
    }

    private function withdraw(Round $round, Uuid $memberId, DateTimeImmutable $now): void
    {
        $withdrawal = $round->withdrawInactiveMember($memberId, $now);
        if (null === $withdrawal || Withdrawal::NotSignedUp === $withdrawal) {
            return;
        }

        $this->auditTrail->record(new AuditedAction('participation.withdrawn_automatically', 'round', $round->id(), [
            'walker' => ['old' => $memberId->toRfc4122(), 'new' => null],
        ]));

        if (Withdrawal::LastWalkerLeft === $withdrawal) {
            $this->rounds->remove($round);
            $this->auditTrail->record(new AuditedAction('round.deleted', 'round', $round->id()));

            return;
        }

        if (Withdrawal::DroppedBelowMinimum !== $withdrawal) {
            return;
        }

        foreach ($round->walkerIds() as $walkerId) {
            $this->notifier->send(new Notification($walkerId, 'round.walker_withdrawn', [
                'roundId' => $round->id()->toRfc4122(),
            ]));
        }
    }
}
