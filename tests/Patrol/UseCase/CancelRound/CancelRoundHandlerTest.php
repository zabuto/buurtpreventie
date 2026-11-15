<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CancelRound;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundNotStarted;
use App\Patrol\Domain\Round\RoundStatus;
use App\Patrol\UseCase\CancelRound\CancelRound;
use App\Patrol\UseCase\CancelRound\CancelRoundHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * The round of RoundFixture started on 20 November 2026 at 21:00 in UTC; the clock reads half an hour later.
 */
final class CancelRoundHandlerTest extends TestCase
{
    private const string NOW = '2026-11-20T21:30:00+00:00';

    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private Round $round;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->round = RoundFixture::withWalkers(Uuid::v7(), 2);
        $this->rounds->add($this->round);
    }

    #[Test]
    public function it_cancels_the_round_with_the_reason(): void
    {
        $this->handler()(new CancelRound($this->round->id()->toRfc4122(), 'Loopmaatje kwam niet'));

        $this->assertSame(
            [RoundStatus::Cancelled, 'Loopmaatje kwam niet'],
            [$this->round->status(new DateTimeImmutable(self::NOW)), $this->round->cancellation()->reason()],
        );
    }

    #[Test]
    public function it_records_the_cancellation_with_the_status_and_reason(): void
    {
        $this->handler()(new CancelRound($this->round->id()->toRfc4122(), 'Loopmaatje kwam niet'));

        $this->assertEquals(
            [new AuditedAction('round.cancelled', 'round', $this->round->id(), [
                'status' => ['old' => 'walked', 'new' => 'cancelled'],
                'cancelReason' => ['old' => null, 'new' => 'Loopmaatje kwam niet'],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_for_a_second_cancellation(): void
    {
        $this->handler()(new CancelRound($this->round->id()->toRfc4122()));

        $this->handler()(new CancelRound($this->round->id()->toRfc4122(), 'Onweer'));

        $this->assertCount(1, $this->auditTrail->recorded);
    }

    #[Test]
    public function it_refuses_to_cancel_before_the_start(): void
    {
        $this->expectExceptionObject(RoundNotStarted::forRound($this->round->id()));

        $this->handler(now: RoundFixture::BEFORE_START)(new CancelRound($this->round->id()->toRfc4122()));
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new CancelRound($this->round->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['round.cancel', $this->round]], $authorization->asked);
        $this->assertSame([false, []], [$this->round->cancellation()->isCancelled(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null, string $now = self::NOW): CancelRoundHandler
    {
        return new CancelRoundHandler(
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock($now),
        );
    }
}
