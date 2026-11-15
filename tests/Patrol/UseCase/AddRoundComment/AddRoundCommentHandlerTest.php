<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\AddRoundComment;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundComment;
use App\Patrol\UseCase\AddRoundComment\AddRoundComment;
use App\Patrol\UseCase\AddRoundComment\AddRoundCommentHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Domain\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class AddRoundCommentHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private InMemoryRoundCommentRepository $comments;
    private RecordingAuditTrail $auditTrail;
    private Uuid $jan;
    private Round $round;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->comments = new InMemoryRoundCommentRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = Uuid::v7();
        $this->round = RoundFixture::withWalkers($this->jan, 1);
        $this->rounds->add($this->round);
    }

    #[Test]
    public function it_posts_the_comment_by_the_actor(): void
    {
        $output = $this->handler()(new AddRoundComment($this->round->id()->toRfc4122(), 'Ik neem een zaklamp mee'));

        $this->assertSame(
            [[$output->id, $this->jan->toRfc4122(), 'Ik neem een zaklamp mee', self::NOW]],
            \array_map(
                static fn(RoundComment $comment): array => [
                    $comment->id()->toRfc4122(),
                    $comment->authorId()->toRfc4122(),
                    $comment->text(),
                    $comment->postedAt()->format(\DATE_ATOM),
                ],
                $this->comments->forRound($this->round),
            ),
        );
    }

    #[Test]
    public function it_records_the_comment(): void
    {
        $output = $this->handler()(new AddRoundComment($this->round->id()->toRfc4122(), 'Ik neem een zaklamp mee'));

        $this->assertEquals(
            [new AuditedAction('comment.added', 'round', $this->round->id(), ['comment' => ['old' => null, 'new' => $output->id]])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new AddRoundComment($this->round->id()->toRfc4122(), 'Ik neem een zaklamp mee'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['comment.add', $this->round]], $authorization->asked);
        $this->assertSame([[], []], [$this->comments->forRound($this->round), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): AddRoundCommentHandler
    {
        return new AddRoundCommentHandler(
            $this->rounds,
            $this->comments,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
            new FixedExecutionContext(new Actor($this->jan, [Role::Walker])),
        );
    }
}
