<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ReactivateMember;

use App\Membership\Domain\Member;
use App\Membership\UseCase\ReactivateMember\ReactivateMember;
use App\Membership\UseCase\ReactivateMember\ReactivateMemberHandler;
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

final class ReactivateMemberHandlerTest extends TestCase
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
        $this->jan->deactivate();
    }

    #[Test]
    public function it_reactivates_the_member(): void
    {
        $this->handler()(new ReactivateMember($this->jan->id()->toRfc4122()));

        $this->assertTrue($this->jan->isActive());
    }

    #[Test]
    public function it_records_that_the_member_was_reactivated(): void
    {
        $this->handler()(new ReactivateMember($this->jan->id()->toRfc4122()));

        $this->assertEquals([new AuditedAction('member.reactivated', 'member', $this->jan->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ReactivateMember($this->jan->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.reactivate', $this->jan]], $authorization->asked);
        $this->assertSame([false, []], [$this->jan->isActive(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): ReactivateMemberHandler
    {
        return new ReactivateMemberHandler(
            $this->members,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
