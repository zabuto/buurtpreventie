<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\InviteMember;

use App\Identity\Domain\CredentialPolicy;
use App\Identity\UseCase\IssueInvitation\IssueInvitationHandler;
use App\Membership\Domain\Member;
use App\Membership\Infrastructure\RepositoryMemberDirectory;
use App\Membership\UseCase\InviteMember\InviteMember;
use App\Membership\UseCase\InviteMember\InviteMemberHandler;
use App\Membership\UseCase\InviteMember\InviteMemberOutput;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Role;
use App\Tests\Identity\Fake\FixedSecretGenerator;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Identity\Fake\InMemoryInvitationRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class InviteMemberHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;
    private InMemoryInvitationRepository $invitations;
    private RecordingNotifier $notifier;
    private RecordingAuditTrail $auditTrail;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->invitations = new InMemoryInvitationRepository();
        $this->notifier = new RecordingNotifier();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    #[Test]
    public function it_has_the_invitation_sent_and_hands_on_its_secret(): void
    {
        $output = $this->handler()(new InviteMember($this->jan->id()->toRfc4122()));

        $this->assertEquals(new InviteMemberOutput('bpi_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'), $output);
        $this->assertSame(['account.invitation'], \array_column($this->notifier->sent, 'type'));
    }

    #[Test]
    public function it_records_the_invitation(): void
    {
        $this->handler()(new InviteMember($this->jan->id()->toRfc4122()));

        $this->assertEquals([new AuditedAction('account.invitation_issued', 'member', $this->jan->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_invites_no_unknown_member(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new InviteMember(Uuid::v7()->toRfc4122()));
    }

    #[Test]
    public function it_invites_nobody_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new InviteMember($this->jan->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['member.invite', null]], $authorization->asked);
        $this->assertSame([[], [], []], [$this->invitations->all(), $this->notifier->sent, $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): InviteMemberHandler
    {
        $authorization ??= FakeAuthorization::granting();

        return new InviteMemberHandler(
            $this->members,
            $authorization,
            new IssueInvitationHandler(
                new RepositoryMemberDirectory($this->members),
                $this->invitations,
                new InMemoryAccountRepository(),
                new FixedSecretGenerator(),
                $this->notifier,
                $authorization,
                new FixedExecutionContext(new Actor(Uuid::v7(), [Role::Admin])),
                new CredentialPolicy(),
                $this->auditTrail,
                new ImmediateTransaction(),
                new MockClock('2026-11-16T10:00:00+00:00'),
            ),
        );
    }
}
