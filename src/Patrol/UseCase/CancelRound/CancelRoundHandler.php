<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CancelRound;

use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Records that a started round was not walked after all, such as after a no-show or force majeure.
 */
#[WriteAction(authorization: 'round.cancel', audit: 'round.cancelled')]
final readonly class CancelRoundHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CancelRound $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $round = $this->rounds->get(Uuid::fromString($input->roundId));
            $this->authorization->denyUnlessGranted('round.cancel', $round);

            $now = $this->clock->now();
            $statusBefore = $round->status($now);
            if (!$round->cancel($input->reason, $now)) {
                return;
            }

            $this->auditTrail->record(new AuditedAction('round.cancelled', 'round', $round->id(), [
                'status' => ['old' => $statusBefore->value, 'new' => $round->status($now)->value],
                'cancelReason' => ['old' => null, 'new' => $round->cancellation()->reason()],
            ]));
        });
    }
}
