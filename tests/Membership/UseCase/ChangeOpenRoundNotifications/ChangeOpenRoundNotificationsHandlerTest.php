<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ChangeOpenRoundNotifications;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberErased;
use App\Membership\UseCase\ChangeOpenRoundNotifications\ChangeOpenRoundNotifications;
use App\Membership\UseCase\ChangeOpenRoundNotifications\ChangeOpenRoundNotificationsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeOpenRoundNotificationsHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;
    private RecordingAuditTrail $auditTrail;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    #[Test]
    public function it_turns_the_notifications_on(): void
    {
        $this->handler()(new ChangeOpenRoundNotifications($this->jan->id()->toRfc4122(), notify: true));

        $this->assertSame([$this->jan], $this->members->activeWithOpenRoundNotifications());
    }

    #[Test]
    public function it_records_the_old_and_new_choice(): void
    {
        $this->handler()(new ChangeOpenRoundNotifications($this->jan->id()->toRfc4122(), notify: true));

        $this->assertEquals(
            [new AuditedAction('member.open_round_notifications_changed', 'member', $this->jan->id(), [
                'notifyOpenRounds' => ['old' => false, 'new' => true],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_refuses_an_erased_member(): void
    {
        $this->jan->erase(new DateTimeImmutable('2026-11-15T10:00:00Z'));

        $refusal = null;
        try {
            $this->handler()(new ChangeOpenRoundNotifications($this->jan->id()->toRfc4122(), notify: true));
        } catch (MemberErased $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(MemberErased::class, $refusal);
        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_asks_whether_the_actor_may_change_this_members_choice(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ChangeOpenRoundNotifications($this->jan->id()->toRfc4122(), notify: true));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.change_open_round_notifications', $this->jan]], $authorization->asked);
        $this->assertSame([[], []], [$this->members->activeWithOpenRoundNotifications(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): ChangeOpenRoundNotificationsHandler
    {
        return new ChangeOpenRoundNotificationsHandler(
            $this->members,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
