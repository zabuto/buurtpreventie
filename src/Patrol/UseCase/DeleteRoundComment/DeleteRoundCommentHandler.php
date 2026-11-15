<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRoundComment;

use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'comment.delete', audit: 'comment.deleted')]
final readonly class DeleteRoundCommentHandler
{
    public function __construct(
        private RoundCommentRepository $comments,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteRoundComment $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $comment = $this->comments->get(Uuid::fromString($input->commentId));
            $this->authorization->denyUnlessGranted('comment.delete', $comment);

            $this->comments->remove($comment);
            $this->auditTrail->record(new AuditedAction('comment.deleted', 'round', $comment->round()->id(), [
                'comment' => ['old' => $comment->id()->toRfc4122(), 'new' => null],
            ]));
        });
    }
}
