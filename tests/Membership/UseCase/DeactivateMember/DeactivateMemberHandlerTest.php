<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\DeactivateMember;

use App\Membership\Domain\Member;
use App\Membership\UseCase\DeactivateMember\DeactivateMember;
use App\Membership\UseCase\DeactivateMember\DeactivateMemberHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeactivateMemberHandlerTest extends TestCase
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
    public function it_deactivates_the_member(): void
    {
        $this->handler()(new DeactivateMember($this->jan->id()->toRfc4122()));

        $this->assertFalse($this->jan->isActive());
    }

    #[Test]
    public function it_records_that_the_member_was_deactivated(): void
    {
        $this->handler()(new DeactivateMember($this->jan->id()->toRfc4122()));

        $this->assertEquals([new AuditedAction('member.deactivated', 'member', $this->jan->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new DeactivateMember($this->jan->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.deactivate', $this->jan]], $authorization->asked);
        $this->assertSame([true, []], [$this->jan->isActive(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): DeactivateMemberHandler
    {
        return new DeactivateMemberHandler(
            $this->members,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
