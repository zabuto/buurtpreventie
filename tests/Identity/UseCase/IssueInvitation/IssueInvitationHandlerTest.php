<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\IssueInvitation;

use App\Identity\Domain\Account;
use App\Identity\Domain\CredentialPolicy;
use App\Identity\Domain\Invitation;
use App\Identity\Domain\InviteeInactive;
use App\Identity\UseCase\IssueInvitation\IssueInvitation;
use App\Identity\UseCase\IssueInvitation\IssueInvitationHandler;
use App\Identity\UseCase\IssueInvitation\IssueInvitationOutput;
use App\Membership\Domain\Member;
use App\Membership\Infrastructure\RepositoryMemberDirectory;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Notification;
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
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class IssueInvitationHandlerTest extends TestCase
{
    private const string NOW = '2026-11-16T10:00:00+00:00';
    // @mago-expect lint:no-literal-password - the made-up secret of FixedSecretGenerator
    private const string SECRET = 'bpi_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private InMemoryMemberRepository $members;
    private InMemoryInvitationRepository $invitations;
    private InMemoryAccountRepository $accounts;
    private RecordingNotifier $notifier;
    private RecordingAuditTrail $auditTrail;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->invitations = new InMemoryInvitationRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->notifier = new RecordingNotifier();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    #[Test]
    public function it_mails_the_member_a_secret_that_opens_the_invitation_for_48_hours(): void
    {
        $this->handler()(new IssueInvitation($this->jan->id()->toRfc4122()));

        $this->assertEquals([new Notification($this->jan->id(), 'account.invitation', ['secret' => self::SECRET, 'hours' => 48])], $this->notifier->sent);
        $invitation = $this->invitations->bySecretHashForUpdate(Invitation::hash(self::SECRET));
        $this->assertSame([true, true], [$invitation?->memberId()->equals($this->jan->id()), $invitation?->isOpenAt(new DateTimeImmutable(self::NOW))]);
        $this->assertEquals(new DateTimeImmutable('2026-11-18T10:00:00+00:00'), $invitation?->expiresAt());
    }

    #[Test]
    public function it_records_the_invitation_without_its_secret(): void
    {
        $this->handler()(new IssueInvitation($this->jan->id()->toRfc4122()));

        $this->assertEquals([new AuditedAction('account.invitation_issued', 'member', $this->jan->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_ends_the_open_invitation_of_the_member(): void
    {
        $earlier = Invitation::issue(
            $this->jan->id(),
            'bpi_fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210',
            new DateTimeImmutable('2026-11-15T10:00:00+00:00'),
        );
        $this->invitations->add($earlier);

        $this->handler()(new IssueInvitation($this->jan->id()->toRfc4122()));

        $this->assertFalse($earlier->isOpenAt(new DateTimeImmutable(self::NOW)));
    }

    #[Test]
    public function it_hands_an_admin_the_secret_of_a_member_who_never_logged_in(): void
    {
        $output = $this->handler(self::actor(Role::Admin))(new IssueInvitation($this->jan->id()->toRfc4122()));

        $this->assertEquals(new IssueInvitationOutput(self::SECRET), $output);
    }

    /**
     * The link would let the admin log in as a member who already has a way in of their own.
     */
    #[Test]
    public function it_withholds_the_secret_of_a_member_who_logged_in_before(): void
    {
        $account = Account::open($this->jan->id());
        $account->recordLogin(new DateTimeImmutable('2026-11-01T10:00:00+00:00'));
        $this->accounts->add($account);

        $output = $this->handler(self::actor(Role::Admin))(new IssueInvitation($this->jan->id()->toRfc4122()));

        $this->assertEquals(new IssueInvitationOutput(null), $output);
    }

    #[Test]
    public function it_withholds_the_secret_from_a_coordinator(): void
    {
        $output = $this->handler(self::actor(Role::Coordinator))(new IssueInvitation($this->jan->id()->toRfc4122()));

        $this->assertEquals(new IssueInvitationOutput(null), $output);
    }

    #[Test]
    public function it_invites_no_deactivated_member(): void
    {
        $this->jan->deactivate();

        $refusal = null;
        try {
            $this->handler()(new IssueInvitation($this->jan->id()->toRfc4122()));
        } catch (InviteeInactive $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(InviteeInactive::class, $refusal);
        $this->assertSame([[], [], []], [$this->invitations->all(), $this->notifier->sent, $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_invites_no_unknown_member(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new IssueInvitation(Uuid::v7()->toRfc4122()));
    }

    #[Test]
    public function it_issues_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler(authorization: $authorization)(new IssueInvitation($this->jan->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['account.issue_invitation', $this->jan->id()]], $authorization->asked);
        $this->assertSame([[], [], []], [$this->invitations->all(), $this->notifier->sent, $this->auditTrail->recorded]);
    }

    private static function actor(Role $role): Actor
    {
        return new Actor(Uuid::v7(), [$role]);
    }

    private function handler(?Actor $actor = null, ?FakeAuthorization $authorization = null): IssueInvitationHandler
    {
        return new IssueInvitationHandler(
            new RepositoryMemberDirectory($this->members),
            $this->invitations,
            $this->accounts,
            new FixedSecretGenerator(),
            $this->notifier,
            $authorization ?? FakeAuthorization::granting(),
            new FixedExecutionContext($actor ?? self::actor(Role::Admin)),
            new CredentialPolicy(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
