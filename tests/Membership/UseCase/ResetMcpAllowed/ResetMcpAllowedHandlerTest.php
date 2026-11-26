<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ResetMcpAllowed;

use App\Membership\Domain\Member;
use App\Membership\UseCase\ResetMcpAllowed\ResetMcpAllowed;
use App\Membership\UseCase\ResetMcpAllowed\ResetMcpAllowedHandler;
use App\Membership\UseCase\ResetMcpAllowed\ResetMcpAllowedOutput;
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

final class ResetMcpAllowedHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;
    private RecordingAuditTrail $auditTrail;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->auditTrail = new RecordingAuditTrail();
    }

    /**
     * Each picked member sees the change in their own activity, so every one gets an entry of their own.
     */
    #[Test]
    public function it_switches_mcp_off_for_every_picked_member_and_records_each(): void
    {
        $jan = $this->member('jan', picked: true);
        $piet = $this->member('piet', picked: true);
        $kees = $this->member('kees', picked: false);

        $output = $this->handler()(new ResetMcpAllowed());

        $this->assertEquals(new ResetMcpAllowedOutput(membersReset: 2), $output);
        $this->assertSame([false, false, false], [$jan->isMcpAllowed(), $piet->isMcpAllowed(), $kees->isMcpAllowed()]);
        $this->assertEqualsCanonicalizing(
            [
                new AuditedAction('member.changed', 'member', $jan->id(), ['mcpAllowed' => ['old' => true, 'new' => false]]),
                new AuditedAction('member.changed', 'member', $piet->id(), ['mcpAllowed' => ['old' => true, 'new' => false]]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_without_picked_members(): void
    {
        $this->member('kees', picked: false);

        $output = $this->handler()(new ResetMcpAllowed());

        $this->assertEquals([new ResetMcpAllowedOutput(membersReset: 0), []], [$output, $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $jan = $this->member('jan', picked: true);
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ResetMcpAllowed());
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.reset_mcp_allowed', null]], $authorization->asked);
        $this->assertSame([true, []], [$jan->isMcpAllowed(), $this->auditTrail->recorded]);
    }

    private function member(string $name, bool $picked): Member
    {
        $member = MemberFixture::stored($this->members, $name . '@example.org', \ucfirst($name) . ' Bakker', \ucfirst($name) . ' B.');
        $member->allowMcp($picked);

        return $member;
    }

    private function handler(?FakeAuthorization $authorization = null): ResetMcpAllowedHandler
    {
        return new ResetMcpAllowedHandler(
            $this->members,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
