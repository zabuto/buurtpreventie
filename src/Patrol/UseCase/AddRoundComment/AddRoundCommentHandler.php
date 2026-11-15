<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\AddRoundComment;

use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'comment.add', audit: 'comment.added')]
final readonly class AddRoundCommentHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private RoundCommentRepository $comments,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
        private ExecutionContext $executionContext,
    ) {
    }

    public function __invoke(AddRoundComment $input): AddRoundCommentOutput
    {
        $actor = $this->executionContext->actor() ?? throw AccessDenied::forAction('comment.add');
        $authorId = $actor->memberId;

        return $this->transaction->run(function () use ($input, $authorId): AddRoundCommentOutput {
            $round = $this->rounds->get(Uuid::fromString($input->roundId));
            $this->authorization->denyUnlessGranted('comment.add', $round);

            $comment = RoundComment::post($round, $authorId, $input->text, $this->clock->now());
            $this->comments->add($comment);
            $this->auditTrail->record(new AuditedAction('comment.added', 'round', $round->id(), [
                'comment' => ['old' => null, 'new' => $comment->id()->toRfc4122()],
            ]));

            return new AddRoundCommentOutput($comment->id()->toRfc4122());
        });
    }
}
