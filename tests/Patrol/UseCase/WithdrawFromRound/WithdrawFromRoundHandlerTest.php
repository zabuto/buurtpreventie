<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\WithdrawFromRound;

use App\Patrol\Domain\Round;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRound;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRoundHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Domain\RoundFixture;
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

final class WithdrawFromRoundHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private RecordingNotifier $notifier;
    private Uuid $jan;
    private Uuid $piet;
    private Uuid $ria;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->jan = Uuid::v7();
        $this->piet = Uuid::v7();
        $this->ria = Uuid::v7();
    }

    #[Test]
    public function it_withdraws_the_actor(): void
    {
        $round = $this->round($this->piet, $this->jan, Uuid::v7());

        $this->handler()(new WithdrawFromRound($round->id()->toRfc4122()));

        $this->assertFalse($round->isSignedUp($this->jan));
    }

    #[Test]
    public function it_asks_whether_the_actor_may_withdraw_the_member(): void
    {
        $round = $this->round($this->piet, $this->jan);
        $authorization = FakeAuthorization::granting();

        $this->handler(authorization: $authorization)(new WithdrawFromRound($round->id()->toRfc4122()));

        $this->assertEquals([['participation.withdraw', $this->jan]], $authorization->asked);
    }

    #[Test]
    public function it_records_a_withdrawal_by_the_walker(): void
    {
        $round = $this->round($this->piet, $this->jan, Uuid::v7());

        $this->handler()(new WithdrawFromRound($round->id()->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('participation.withdrawn', 'round', $round->id(), ['walker' => ['old' => $this->jan->toRfc4122(), 'new' => null]])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_a_walker_removed_by_someone_else(): void
    {
        $round = $this->round($this->piet, $this->jan, Uuid::v7());

        $this->handler(actor: new Actor($this->ria, [Role::Coordinator]))(new WithdrawFromRound($round->id()->toRfc4122(), $this->jan->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('participation.walker_removed', 'round', $round->id(), ['walker' => ['old' => $this->jan->toRfc4122(), 'new' => null]])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_deletes_and_records_the_round_when_the_last_walker_withdraws(): void
    {
        $round = $this->round($this->jan);

        $this->handler()(new WithdrawFromRound($round->id()->toRfc4122()));

        $this->assertFalse($this->rounds->has($round));
        $this->assertSame(
            ['participation.withdrawn', 'round.deleted'],
            \array_map(static fn(AuditedAction $action): string => $action->action, $this->auditTrail->recorded),
        );
    }

    #[Test]
    public function it_tells_the_remaining_walker_when_the_round_drops_below_the_minimum(): void
    {
        $round = $this->round($this->piet, $this->jan);

        $this->handler()(new WithdrawFromRound($round->id()->toRfc4122()));

        $this->assertEquals([new Notification($this->piet, 'round.walker_withdrawn', ['roundId' => $round->id()->toRfc4122()])], $this->notifier->sent);
    }

    #[Test]
    public function it_tells_nobody_when_the_round_keeps_its_minimum(): void
    {
        $round = $this->round($this->piet, $this->jan, Uuid::v7());

        $this->handler()(new WithdrawFromRound($round->id()->toRfc4122()));

        $this->assertSame([], $this->notifier->sent);
    }

    /**
     * Withdrawing twice, such as after a retry, changes nothing and fails nothing.
     */
    #[Test]
    public function it_records_nothing_for_a_member_who_is_not_signed_up(): void
    {
        $round = $this->round($this->piet, Uuid::v7());

        $this->handler()(new WithdrawFromRound($round->id()->toRfc4122()));

        $this->assertSame([2, [], []], [$round->walkerCount(), $this->auditTrail->recorded, $this->notifier->sent]);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $round = $this->round($this->piet, $this->jan);
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler(authorization: $authorization)(new WithdrawFromRound($round->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['participation.withdraw', $this->jan]], $authorization->asked);
        $this->assertSame([true, [], []], [$round->isSignedUp($this->jan), $this->auditTrail->recorded, $this->notifier->sent]);
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

    private function handler(?Actor $actor = null, ?FakeAuthorization $authorization = null): WithdrawFromRoundHandler
    {
        return new WithdrawFromRoundHandler(
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            $this->notifier,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
            new FixedExecutionContext($actor ?? new Actor($this->jan, [Role::Walker])),
        );
    }
}
