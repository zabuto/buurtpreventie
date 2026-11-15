<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\DeactivateMember;

use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokensHandler;
use App\Membership\Domain\Member;
use App\Membership\UseCase\DeactivateMember\DeactivateMember;
use App\Membership\UseCase\DeactivateMember\DeactivateMemberHandler;
use App\Patrol\Domain\Round;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRoundsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Identity\Fake\ApiTokenFixture;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Domain\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Runs the Patrol and Identity handlers it calls for real, with in-memory storage, so the test sees the member leave
 * the round and the token stop working.
 */
final class DeactivateMemberHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryMemberRepository $members;
    private InMemoryRoundRepository $rounds;
    private InMemoryApiTokenRepository $tokens;
    private RecordingAuditTrail $auditTrail;
    private ImmediateTransaction $transaction;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->rounds = new InMemoryRoundRepository();
        $this->tokens = new InMemoryApiTokenRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->transaction = new ImmediateTransaction();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    #[Test]
    public function it_deactivates_the_member(): void
    {
        $this->handler()(new DeactivateMember($this->jan->id()->toRfc4122()));

        $this->assertFalse($this->jan->isActive());
    }

    #[Test]
    public function it_withdraws_the_member_from_rounds_that_have_not_started(): void
    {
        $round = $this->upcomingRoundWithJan();

        $this->handler()(new DeactivateMember($this->jan->id()->toRfc4122()));

        $this->assertFalse($round->isSignedUp($this->jan->id()));
    }

    #[Test]
    public function it_revokes_every_token_of_the_member(): void
    {
        $token = ApiTokenFixture::stored($this->tokens, $this->jan->id());

        $this->handler()(new DeactivateMember($this->jan->id()->toRfc4122()));

        $this->assertFalse($token->isUsableAt(new DateTimeImmutable(self::NOW)));
    }

    #[Test]
    public function it_runs_the_withdrawal_and_the_revocation_inside_its_own_transaction(): void
    {
        $this->handler()(new DeactivateMember($this->jan->id()->toRfc4122()));

        $this->assertSame([1, 2, 2], $this->transaction->depths);
    }

    #[Test]
    public function it_records_the_deactivation_the_withdrawal_and_the_revocation(): void
    {
        $round = $this->upcomingRoundWithJan();
        $token = ApiTokenFixture::stored($this->tokens, $this->jan->id());

        $this->handler()(new DeactivateMember($this->jan->id()->toRfc4122()));

        $this->assertEquals(
            [
                new AuditedAction('member.deactivated', 'member', $this->jan->id()),
                new AuditedAction('participation.withdrawn_automatically', 'round', $round->id(), [
                    'walker' => ['old' => $this->jan->id()->toRfc4122(), 'new' => null],
                ]),
                new AuditedAction('api_token.revoked', 'api_token', $token->id()),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $round = $this->upcomingRoundWithJan();
        $token = ApiTokenFixture::stored($this->tokens, $this->jan->id());
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new DeactivateMember($this->jan->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.deactivate', $this->jan]], $authorization->asked);
        $this->assertSame(
            [true, true, null, []],
            [$this->jan->isActive(), $round->isSignedUp($this->jan->id()), $token->revokedAt(), $this->auditTrail->recorded],
        );
    }

    private function upcomingRoundWithJan(): Round
    {
        $round = RoundFixture::withWalkers($this->jan->id(), 2);
        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FakeAuthorization $authorization = null): DeactivateMemberHandler
    {
        $authorization ??= FakeAuthorization::granting();
        $clock = new MockClock(self::NOW);

        return new DeactivateMemberHandler(
            $this->members,
            $authorization,
            $this->auditTrail,
            $this->transaction,
            new WithdrawMemberFromUpcomingRoundsHandler(
                $this->rounds,
                $authorization,
                $this->auditTrail,
                new RecordingNotifier(),
                $this->transaction,
                $clock,
            ),
            new RevokeMemberApiTokensHandler($this->tokens, $authorization, $this->auditTrail, $this->transaction, $clock),
        );
    }
}
