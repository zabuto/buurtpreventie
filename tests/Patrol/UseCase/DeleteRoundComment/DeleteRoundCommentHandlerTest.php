<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\DeleteRoundComment;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundComment;
use App\Patrol\UseCase\DeleteRoundComment\DeleteRoundComment;
use App\Patrol\UseCase\DeleteRoundComment\DeleteRoundCommentHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Patrol\Domain\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DeleteRoundCommentHandlerTest extends TestCase
{
    private InMemoryRoundCommentRepository $comments;
    private RecordingAuditTrail $auditTrail;
    private Round $round;
    private RoundComment $comment;

    #[Override]
    protected function setUp(): void
    {
        $this->comments = new InMemoryRoundCommentRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $jan = Uuid::v7();
        $this->round = RoundFixture::withWalkers($jan, 1);
        $this->comment = RoundComment::post($this->round, $jan, 'Ik neem een zaklamp mee', new DateTimeImmutable(RoundFixture::CREATED_AT));
        $this->comments->add($this->comment);
    }

    #[Test]
    public function it_deletes_the_comment(): void
    {
        $this->handler()(new DeleteRoundComment($this->comment->id()->toRfc4122()));

        $this->assertSame([], $this->comments->forRound($this->round));
    }

    #[Test]
    public function it_records_the_deletion(): void
    {
        $this->handler()(new DeleteRoundComment($this->comment->id()->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('comment.deleted', 'round', $this->round->id(), ['comment' => ['old' => $this->comment->id()->toRfc4122(), 'new' => null]])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new DeleteRoundComment($this->comment->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['comment.delete', $this->comment]], $authorization->asked);
        $this->assertSame([[$this->comment], []], [$this->comments->forRound($this->round), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): DeleteRoundCommentHandler
    {
        return new DeleteRoundCommentHandler(
            $this->comments,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
