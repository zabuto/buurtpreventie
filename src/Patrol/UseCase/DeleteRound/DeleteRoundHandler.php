<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRound;

use App\Patrol\Domain\Round\RoundRepository;
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
 * Deletes a round that has not started; its walkers, report and comments go with it. The other walkers hear of it,
 * as they counted on the round.
 */
#[WriteAction(authorization: 'round.delete', audit: 'round.deleted')]
final readonly class DeleteRoundHandler
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

    public function __invoke(DeleteRound $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $round = $this->rounds->get(Uuid::fromString($input->roundId));
            $this->authorization->denyUnlessGranted('round.delete', $round);

            $round->ensureDeletable($this->clock->now());

            $this->rounds->remove($round);
            $this->auditTrail->record(new AuditedAction('round.deleted', 'round', $round->id()));

            $actorId = $this->executionContext->actor()?->memberId;
            foreach ($round->walkerIds() as $walkerId) {
                if (null !== $actorId && $walkerId->equals($actorId)) {
                    continue;
                }

                $this->notifier->send(new Notification($walkerId, 'round.cancelled', ['roundId' => $round->id()->toRfc4122()]));
            }
        });
    }
}
