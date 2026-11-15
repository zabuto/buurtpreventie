<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\SignUpForRound;

use App\Patrol\Domain\MemberInactive;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundIsFull;
use App\Patrol\UseCase\SignUpForRound\SignUpForRound;
use App\Patrol\UseCase\SignUpForRound\SignUpForRoundHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Domain\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\FixedMemberDirectory;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class SignUpForRoundHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private Uuid $jan;
    private Uuid $ria;
    private Round $round;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = Uuid::v7();
        $this->ria = Uuid::v7();
        $this->round = RoundFixture::withWalkers(Uuid::v7(), 1, maximum: 3);
        $this->rounds->add($this->round);
    }

    #[Test]
    public function it_signs_the_actor_up(): void
    {
        $this->handler()(new SignUpForRound($this->round->id()->toRfc4122()));

        $this->assertTrue($this->round->isSignedUp($this->jan));
        $this->assertNull($this->round->participationOf($this->jan)?->onBehalfOf());
    }

    #[Test]
    public function it_asks_whether_the_actor_may_sign_up_the_member(): void
    {
        $authorization = FakeAuthorization::granting();

        $this->handler(authorization: $authorization)(new SignUpForRound($this->round->id()->toRfc4122()));

        $this->assertEquals([['participation.sign_up', $this->jan]], $authorization->asked);
    }

    #[Test]
    public function it_signs_up_another_member_on_their_behalf(): void
    {
        $this->handler(actor: new Actor($this->ria, [Role::Coordinator]))(new SignUpForRound($this->round->id()->toRfc4122(), $this->jan->toRfc4122()));

        $this->assertEquals($this->ria, $this->round->participationOf($this->jan)?->onBehalfOf());
    }

    #[Test]
    public function it_records_the_sign_up(): void
    {
        $this->handler()(new SignUpForRound($this->round->id()->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('participation.signed_up', 'round', $this->round->id(), ['walker' => ['old' => null, 'new' => $this->jan->toRfc4122()]])],
            $this->auditTrail->recorded,
        );
    }

    /**
     * A double click or a retry must not fail, nor leave a second trace.
     */
    #[Test]
    public function it_records_nothing_for_a_second_sign_up(): void
    {
        $this->handler()(new SignUpForRound($this->round->id()->toRfc4122()));

        $this->handler()(new SignUpForRound($this->round->id()->toRfc4122()));

        $this->assertSame([2, 1], [$this->round->walkerCount(), \count($this->auditTrail->recorded)]);
    }

    #[Test]
    public function it_refuses_a_sign_up_on_behalf_in_a_full_round(): void
    {
        $this->round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::CREATED_AT));
        $this->round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::CREATED_AT));

        $this->expectExceptionObject(RoundIsFull::forRound($this->round->id()));

        $this->handler(actor: new Actor($this->ria, [Role::Coordinator]))(new SignUpForRound($this->round->id()->toRfc4122(), $this->jan->toRfc4122()));
    }

    #[Test]
    public function it_refuses_a_member_who_is_not_active(): void
    {
        $directory = new FixedMemberDirectory([new MemberProfile($this->jan, 'Jan B.', 'Jan Bakker', null, active: false)]);

        $refusal = null;
        try {
            $this->handler(actor: new Actor($this->ria, [Role::Coordinator]), directory: $directory)(
                new SignUpForRound($this->round->id()->toRfc4122(), $this->jan->toRfc4122()),
            );
        } catch (MemberInactive $exception) {
            $refusal = $exception;
        }

        $this->assertEquals(MemberInactive::forMember($this->jan), $refusal);
        $this->assertSame([false, []], [$this->round->isSignedUp($this->jan), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler(authorization: $authorization)(new SignUpForRound($this->round->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['participation.sign_up', $this->jan]], $authorization->asked);
        $this->assertSame([false, []], [$this->round->isSignedUp($this->jan), $this->auditTrail->recorded]);
    }

    private function handler(
        ?Actor $actor = null,
        ?FakeAuthorization $authorization = null,
        ?FixedMemberDirectory $directory = null,
    ): SignUpForRoundHandler {
        return new SignUpForRoundHandler(
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
            new FixedExecutionContext($actor ?? new Actor($this->jan, [Role::Walker])),
            $directory ?? new FixedMemberDirectory([
                FixedMemberDirectory::activeProfile($this->jan),
                FixedMemberDirectory::activeProfile($this->ria, 'Ria S.'),
            ]),
        );
    }
}
