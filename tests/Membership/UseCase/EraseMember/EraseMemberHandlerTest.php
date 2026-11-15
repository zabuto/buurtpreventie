<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\EraseMember;

use App\Audit\UseCase\EraseActorName\EraseActorNameHandler;
use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokensHandler;
use App\Membership\Domain\LastAdmin;
use App\Membership\Domain\LastAdminGuard;
use App\Membership\Domain\Member;
use App\Membership\Domain\UniqueMemberDetails;
use App\Membership\UseCase\AddMember\AddMember;
use App\Membership\UseCase\AddMember\AddMemberHandler;
use App\Membership\UseCase\EraseMember\EraseMember;
use App\Membership\UseCase\EraseMember\EraseMemberHandler;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRoundsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Role;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Identity\Fake\ApiTokenFixture;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Domain\Round\RoundFixture;
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

final class EraseMemberHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryMemberRepository $members;
    private InMemoryRoundRepository $rounds;
    private InMemoryApiTokenRepository $tokens;
    private RecordingAuditEntryRepository $auditEntries;
    private RecordingAuditTrail $auditTrail;
    private ImmediateTransaction $transaction;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->rounds = new InMemoryRoundRepository();
        $this->tokens = new InMemoryApiTokenRepository();
        $this->auditEntries = new RecordingAuditEntryRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->transaction = new ImmediateTransaction();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    #[Test]
    public function it_erases_the_member_at_the_current_time(): void
    {
        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertSame([null, null], [$this->jan->email(), $this->jan->displayName()]);
        $this->assertEquals(new DateTimeImmutable(self::NOW), $this->jan->erasedAt());
    }

    #[Test]
    public function it_withdraws_the_member_from_rounds_that_have_not_started(): void
    {
        $round = RoundFixture::withWalkers($this->jan->id(), 2);
        $this->rounds->add($round);

        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertFalse($round->isSignedUp($this->jan->id()));
    }

    #[Test]
    public function it_revokes_every_token_of_the_member(): void
    {
        $token = ApiTokenFixture::stored($this->tokens, $this->jan->id());

        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertFalse($token->isUsableAt(new DateTimeImmutable(self::NOW)));
    }

    #[Test]
    public function it_erases_the_name_of_the_member_from_the_audit_log(): void
    {
        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertEquals([$this->jan->id()], $this->auditEntries->erasedActorNames);
    }

    #[Test]
    public function it_runs_each_consequence_inside_its_own_transaction(): void
    {
        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertSame([1, 2, 2, 2], $this->transaction->depths);
    }

    #[Test]
    public function it_records_the_erasure_without_personal_data_then_each_consequence(): void
    {
        $round = RoundFixture::withWalkers($this->jan->id(), 2);
        $this->rounds->add($round);
        $token = ApiTokenFixture::stored($this->tokens, $this->jan->id());

        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertEquals(
            [
                new AuditedAction('member.erased', 'member', $this->jan->id()),
                new AuditedAction('participation.withdrawn_automatically', 'round', $round->id(), [
                    'walker' => ['old' => $this->jan->id()->toRfc4122(), 'new' => null],
                ]),
                new AuditedAction('api_token.revoked', 'api_token', $token->id()),
                new AuditedAction('audit.actor_name_erased', 'member', $this->jan->id()),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_lets_two_erased_members_exist_side_by_side_without_a_display_name(): void
    {
        $piet = MemberFixture::stored($this->members, 'piet@example.org', 'Piet Jansen', 'Piet J.');
        $handler = $this->handler();
        $handler(new EraseMember($this->jan->id()->toRfc4122()));

        $handler(new EraseMember($piet->id()->toRfc4122()));

        $this->assertSame([null, null], [$this->jan->displayNameKey(), $piet->displayNameKey()]);
    }

    #[Test]
    public function it_frees_the_address_and_display_name_for_a_new_member(): void
    {
        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));
        $addMember = new AddMemberHandler(
            $this->members,
            new UniqueMemberDetails($this->members),
            FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );

        $addMember(new AddMember(email: 'jan@example.org', name: 'Jan Bakker', displayName: 'Jan B.'));

        $this->assertCount(2, $this->members->all());
    }

    #[Test]
    public function it_erases_nothing_when_access_is_denied(): void
    {
        $token = ApiTokenFixture::stored($this->tokens, $this->jan->id());
        $refusal = null;
        try {
            $this->handler(FakeAuthorization::refusing())(new EraseMember($this->jan->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([false, null, []], [$this->jan->isErased(), $token->revokedAt(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_refuses_to_erase_the_last_admin(): void
    {
        $this->jan->changeRoles([Role::Admin]);

        $refusal = null;
        try {
            $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));
        } catch (LastAdmin $exception) {
            $refusal = $exception;
        }

        $this->assertEquals(LastAdmin::forMember($this->jan->id()), $refusal);
        $this->assertSame([false, []], [$this->jan->isErased(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_erases_an_admin_when_another_active_admin_remains(): void
    {
        $this->jan->changeRoles([Role::Admin]);
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.', [Role::Admin]);

        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertTrue($this->jan->isErased());
    }

    private function handler(?FakeAuthorization $authorization = null): EraseMemberHandler
    {
        $authorization ??= FakeAuthorization::granting();
        $clock = new MockClock(self::NOW);

        return new EraseMemberHandler(
            $this->members,
            $authorization,
            $this->auditTrail,
            $this->transaction,
            $clock,
            new LastAdminGuard($this->members),
            new WithdrawMemberFromUpcomingRoundsHandler(
                $this->rounds,
                $authorization,
                $this->auditTrail,
                new RecordingNotifier(),
                $this->transaction,
                $clock,
            ),
            new RevokeMemberApiTokensHandler($this->tokens, $authorization, $this->auditTrail, $this->transaction, $clock),
            new EraseActorNameHandler($this->auditEntries, $authorization, $this->auditTrail, $this->transaction),
        );
    }
}
