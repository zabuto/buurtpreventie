<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SignUpForRound;

use App\Patrol\Domain\Round\MemberInactive;
use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Signs a member up, the actor or, on their behalf, another active member. Signing up twice changes nothing and
 * fails nothing.
 */
#[WriteAction(authorization: 'participation.sign_up', audit: 'participation.signed_up')]
final readonly class SignUpForRoundHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
        private ExecutionContext $executionContext,
        private MemberDirectory $members,
    ) {
    }

    public function __invoke(SignUpForRound $input): void
    {
        $actorId = $this->executionContext->actor()?->memberId;
        $memberId = null === $input->memberId ? $actorId : Uuid::fromString($input->memberId);
        if (null === $memberId) {
            throw AccessDenied::forAction('participation.sign_up');
        }

        $this->authorization->denyUnlessGranted('participation.sign_up', $memberId);

        $this->transaction->run(function () use ($input, $memberId, $actorId): void {
            $round = $this->rounds->getForUpdate(Uuid::fromString($input->roundId));
            if (!$this->members->isActive($memberId)) {
                throw MemberInactive::forMember($memberId);
            }

            $onBehalfOf = null !== $actorId && !$actorId->equals($memberId) ? $actorId : null;
            if (!$round->signUp($memberId, $this->clock->now(), $onBehalfOf)) {
                return;
            }

            $this->auditTrail->record(new AuditedAction('participation.signed_up', 'round', $round->id(), [
                'walker' => ['old' => null, 'new' => $memberId->toRfc4122()],
            ]));
        });
    }
}
