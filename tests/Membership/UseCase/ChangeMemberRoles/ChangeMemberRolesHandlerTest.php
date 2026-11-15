<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ChangeMemberRoles;

use App\Membership\Domain\LastAdmin;
use App\Membership\Domain\LastAdminGuard;
use App\Membership\Domain\Member;
use App\Membership\UseCase\ChangeMemberRoles\ChangeMemberRoles;
use App\Membership\UseCase\ChangeMemberRoles\ChangeMemberRolesHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeMemberRolesHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;
    private RecordingAuditTrail $auditTrail;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.', [Role::Walker]);
    }

    #[Test]
    public function it_replaces_the_roles(): void
    {
        $this->handler()(new ChangeMemberRoles($this->jan->id()->toRfc4122(), ['coordinator', 'admin']));

        $this->assertSame([Role::Coordinator, Role::Admin], $this->jan->roles());
    }

    #[Test]
    public function it_records_the_old_and_new_roles(): void
    {
        $this->handler()(new ChangeMemberRoles($this->jan->id()->toRfc4122(), ['walker', 'coordinator']));

        $this->assertEquals(
            [new AuditedAction('member.roles_changed', 'member', $this->jan->id(), [
                'roles' => ['old' => 'walker', 'new' => 'walker,coordinator'],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $refusal = null;
        try {
            $this->handler(FakeAuthorization::refusing())(new ChangeMemberRoles($this->jan->id()->toRfc4122(), ['admin']));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([[Role::Walker], []], [$this->jan->roles(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_refuses_to_take_the_admin_role_from_the_last_admin(): void
    {
        $this->jan->changeRoles([Role::Admin]);

        $refusal = null;
        try {
            $this->handler()(new ChangeMemberRoles($this->jan->id()->toRfc4122(), ['walker']));
        } catch (LastAdmin $exception) {
            $refusal = $exception;
        }

        $this->assertEquals(LastAdmin::forMember($this->jan->id()), $refusal);
        $this->assertSame([[Role::Admin], []], [$this->jan->roles(), $this->auditTrail->recorded]);
    }

    /**
     * A deactivated admin cannot log in, so it manages nothing.
     */
    #[Test]
    public function it_refuses_to_take_the_admin_role_when_the_only_other_admin_is_deactivated(): void
    {
        $this->jan->changeRoles([Role::Admin]);
        $piet = MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.', [Role::Admin]);
        $piet->deactivate();

        $this->expectException(LastAdmin::class);

        $this->handler()(new ChangeMemberRoles($this->jan->id()->toRfc4122(), ['walker']));
    }

    #[Test]
    public function it_takes_the_admin_role_when_another_active_admin_remains(): void
    {
        $this->jan->changeRoles([Role::Admin]);
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.', [Role::Admin]);

        $this->handler()(new ChangeMemberRoles($this->jan->id()->toRfc4122(), ['walker']));

        $this->assertSame([Role::Walker], $this->jan->roles());
    }

    #[Test]
    public function it_lets_the_last_admin_keep_the_admin_role_while_other_roles_change(): void
    {
        $this->jan->changeRoles([Role::Admin]);

        $this->handler()(new ChangeMemberRoles($this->jan->id()->toRfc4122(), ['admin', 'coordinator']));

        $this->assertSame([Role::Admin, Role::Coordinator], $this->jan->roles());
    }

    private function handler(?FakeAuthorization $authorization = null): ChangeMemberRolesHandler
    {
        return new ChangeMemberRolesHandler(
            $this->members,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new LastAdminGuard($this->members),
        );
    }
}
