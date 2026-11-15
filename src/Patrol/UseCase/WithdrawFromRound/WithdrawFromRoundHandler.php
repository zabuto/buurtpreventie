<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\WithdrawFromRound;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\Withdrawal;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Withdraws a member: the actor, or a walker a coordinator removes. A round left without walkers goes; a round that
 * drops below its minimum tells the walkers who stay, so they can find a buddy.
 */
#[WriteAction(authorization: 'participation.withdraw', audit: 'participation.withdrawn')]
final readonly class WithdrawFromRoundHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Notifier $notifier,
        private Transaction $transaction,
        private ClockInterface $clock,
        private ExecutionContext $executionContext,
    ) {
    }

    public function __invoke(WithdrawFromRound $input): void
    {
        $actorId = $this->executionContext->actor()?->memberId;
        $memberId = null === $input->memberId ? $actorId : Uuid::fromString($input->memberId);
        if (null === $memberId) {
            throw AccessDenied::forAction('participation.withdraw');
        }

        $this->authorization->denyUnlessGranted('participation.withdraw', $memberId);

        $this->transaction->run(function () use ($input, $memberId, $actorId): void {
            $round = $this->rounds->getForUpdate(Uuid::fromString($input->roundId));

            $withdrawal = $round->withdraw($memberId, $this->clock->now());
            if (Withdrawal::NotSignedUp === $withdrawal) {
                return;
            }

            $action = null === $actorId || $actorId->equals($memberId) ? 'participation.withdrawn' : 'participation.walker_removed';
            $this->auditTrail->record(new AuditedAction($action, 'round', $round->id(), [
                'walker' => ['old' => $memberId->toRfc4122(), 'new' => null],
            ]));

            if (Withdrawal::LastWalkerLeft === $withdrawal) {
                $this->rounds->remove($round);
                $this->auditTrail->record(new AuditedAction('round.deleted', 'round', $round->id()));

                return;
            }

            if (Withdrawal::DroppedBelowMinimum === $withdrawal) {
                $this->tellRemainingWalkers($round);
            }
        });
    }

    private function tellRemainingWalkers(Round $round): void
    {
        foreach ($round->walkerIds() as $walkerId) {
            $this->notifier->send(new Notification($walkerId, 'round.walker_withdrawn', ['roundId' => $round->id()->toRfc4122()]));
        }
    }
}
