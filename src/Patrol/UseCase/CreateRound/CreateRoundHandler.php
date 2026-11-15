<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateRound;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Patrol\Domain\Round\BuddyCaller;
use App\Patrol\Domain\Round\MemberInactive;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Settings\UseCase\GetStaffingDefaults\GetStaffingDefaults;
use App\Settings\UseCase\GetStaffingDefaults\GetStaffingDefaultsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Plans a round with its first walker, who owns it; a coordinator may plan it on behalf of an active member. Who
 * created it is in the audit log. A round less than a day ahead gets its buddy call at once, so it arrives without
 * a scheduler.
 */
#[WriteAction(authorization: 'round.create', audit: 'round.created')]
final readonly class CreateRoundHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private MeetingPointRepository $meetingPoints,
        private RoundCommentRepository $comments,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
        private ExecutionContext $executionContext,
        private MemberDirectory $members,
        private BuddyCaller $buddyCaller,
        private GetStaffingDefaultsHandler $staffingDefaults,
    ) {
    }

    public function __invoke(CreateRound $input): CreateRoundOutput
    {
        $actorId = $this->executionContext->actor()?->memberId;
        $firstWalker = null === $input->firstWalkerId ? $actorId : Uuid::fromString($input->firstWalkerId);
        if (null === $firstWalker) {
            throw AccessDenied::forAction('round.create');
        }

        $this->authorization->denyUnlessGranted('round.create', $firstWalker);

        return $this->transaction->run(function () use ($input, $firstWalker, $actorId): CreateRoundOutput {
            $now = $this->clock->now();
            $round = $this->plan($input, $firstWalker, $actorId, $now);

            $comment = \trim($input->comment ?? '');
            if ('' !== $comment) {
                $this->comment($round, $actorId ?? $firstWalker, $comment, $now);
            }

            $recipients = $this->buddyCaller->callIfDue($round, $now);
            if (null !== $recipients) {
                $this->auditTrail->record(new AuditedAction('round.buddy_call_sent', 'round', $round->id(), [
                    'recipients' => ['old' => null, 'new' => $recipients],
                ]));
            }

            return new CreateRoundOutput($round->id()->toRfc4122());
        });
    }

    private function plan(CreateRound $input, Uuid $firstWalker, ?Uuid $actorId, DateTimeImmutable $now): Round
    {
        if (!$this->members->isActive($firstWalker)) {
            throw MemberInactive::forMember($firstWalker);
        }

        $defaults = ($this->staffingDefaults)(new GetStaffingDefaults());
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($input->startsAt), $this->meetingPoint($input->meetingPointId), $input->startNote),
            $firstWalker,
            new StaffingRule($defaults->minimumWalkers, $defaults->maximumWalkers),
            $now,
            null !== $actorId && !$actorId->equals($firstWalker) ? $actorId : null,
        );
        $this->rounds->add($round);
        $this->auditTrail->record(new AuditedAction('round.created', 'round', $round->id(), [
            'startsAt' => ['old' => null, 'new' => $round->startsAt()->format(\DATE_ATOM)],
            'firstWalker' => ['old' => null, 'new' => $firstWalker->toRfc4122()],
        ]));

        return $round;
    }

    private function meetingPoint(?string $id): ?MeetingPoint
    {
        if (null === $id) {
            return null;
        }

        return $this->meetingPoints->get(Uuid::fromString($id));
    }

    private function comment(Round $round, Uuid $authorId, string $text, DateTimeImmutable $now): void
    {
        $comment = RoundComment::post($round, $authorId, $text, $now);
        $this->comments->add($comment);
        $this->auditTrail->record(new AuditedAction('comment.added', 'round', $round->id(), [
            'comment' => ['old' => null, 'new' => $comment->id()->toRfc4122()],
        ]));
    }
}
