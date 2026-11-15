<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\DeleteRound;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundHasStarted;
use App\Patrol\UseCase\DeleteRound\DeleteRound;
use App\Patrol\UseCase\DeleteRound\DeleteRoundHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class DeleteRoundHandlerTest extends TestCase
{
    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private RecordingNotifier $notifier;
    private Uuid $ria;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->ria = Uuid::v7();
    }

    #[Test]
    public function it_deletes_the_round(): void
    {
        $round = $this->round(Uuid::v7());

        $this->handler()(new DeleteRound($round->id()->toRfc4122()));

        $this->assertFalse($this->rounds->has($round));
    }

    #[Test]
    public function it_records_the_deletion(): void
    {
        $round = $this->round(Uuid::v7());

        $this->handler()(new DeleteRound($round->id()->toRfc4122()));

        $this->assertEquals([new AuditedAction('round.deleted', 'round', $round->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_tells_every_walker_but_the_actor_that_the_round_is_cancelled(): void
    {
        $jan = Uuid::v7();
        $round = $this->round($jan, $this->ria);

        $this->handler()(new DeleteRound($round->id()->toRfc4122()));

        $this->assertEquals([new Notification($jan, 'round.cancelled', ['roundId' => $round->id()->toRfc4122()])], $this->notifier->sent);
    }

    /**
     * Who walked when is history from the start; deleting the round would erase it.
     */
    #[Test]
    public function it_refuses_to_delete_a_round_that_has_started(): void
    {
        $round = $this->round(Uuid::v7());

        $this->expectExceptionObject(RoundHasStarted::forRound($round->id()));

        $this->handler(now: RoundFixture::AT_START)(new DeleteRound($round->id()->toRfc4122()));
    }

    #[Test]
    public function it_deletes_a_round_a_minute_before_it_starts(): void
    {
        $round = $this->round(Uuid::v7());

        $this->handler(now: '2026-11-20T20:59:00+00:00')(new DeleteRound($round->id()->toRfc4122()));

        $this->assertFalse($this->rounds->has($round));
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $round = $this->round(Uuid::v7());
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler(authorization: $authorization)(new DeleteRound($round->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['round.delete', $round]], $authorization->asked);
        $this->assertSame([true, [], []], [$this->rounds->has($round), $this->auditTrail->recorded, $this->notifier->sent]);
    }

    private function round(Uuid $firstWalker, Uuid ...$others): Round
    {
        $round = RoundFixture::withWalkers($firstWalker, 1);
        foreach ($others as $walker) {
            $round->signUp($walker, new DateTimeImmutable(RoundFixture::CREATED_AT));
        }
        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FakeAuthorization $authorization = null, string $now = '2026-11-15T12:00:00+00:00'): DeleteRoundHandler
    {
        return new DeleteRoundHandler(
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            $this->notifier,
            new ImmediateTransaction(),
            new MockClock($now),
            new FixedExecutionContext(new Actor($this->ria, [Role::Coordinator])),
        );
    }
}
