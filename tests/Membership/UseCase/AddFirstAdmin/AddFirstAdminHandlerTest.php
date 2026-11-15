<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\AddFirstAdmin;

use App\Membership\Domain\AdminExists;
use App\Membership\Domain\EmailAddressTaken;
use App\Membership\Domain\FirstAdminGuard;
use App\Membership\Domain\UniqueMemberDetails;
use App\Membership\UseCase\AddFirstAdmin\AddFirstAdmin;
use App\Membership\UseCase\AddFirstAdmin\AddFirstAdminHandler;
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
use Symfony\Component\Uid\Uuid;

final class AddFirstAdminHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;
    private RecordingAuditTrail $auditTrail;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->auditTrail = new RecordingAuditTrail();
    }

    #[Test]
    public function it_adds_an_admin_with_details(): void
    {
        $output = $this->handler()(new AddFirstAdmin(
            email: 'Beheer@Example.org',
            name: 'Bea Beheer',
            displayName: 'Bea',
            phone: '06 1234 5678',
            address: 'Dorpsstraat 1',
        ));

        $member = $this->members->get(Uuid::fromString($output->id));
        $this->assertSame(
            ['beheer@example.org', 'Bea Beheer', 'Bea', '06 1234 5678', 'Dorpsstraat 1', [Role::Admin], true],
            [
                $member->email()?->value,
                $member->name(),
                $member->displayName(),
                $member->phone()?->value,
                $member->address(),
                $member->roles(),
                $member->isActive(),
            ],
        );
    }

    #[Test]
    public function it_adds_an_admin_when_the_only_admin_is_deactivated(): void
    {
        MemberFixture::stored($this->members, 'oud@example.org', 'Oude Beheerder', 'Oud', [Role::Admin])->deactivate();

        $this->handler()(new AddFirstAdmin(email: 'beheer@example.org', name: 'Bea Beheer', displayName: 'Bea'));

        $this->assertCount(2, $this->members->all());
    }

    #[Test]
    public function it_refuses_while_an_active_admin_exists(): void
    {
        $admin = MemberFixture::stored($this->members, 'oud@example.org', 'Oude Beheerder', 'Oud', [Role::Admin]);

        $refusal = null;
        try {
            $this->handler()(new AddFirstAdmin(email: 'beheer@example.org', name: 'Bea Beheer', displayName: 'Bea'));
        } catch (AdminExists $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AdminExists::class, $refusal);
        $this->assertSame([[$admin], []], [$this->members->all(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_refuses_an_email_address_another_member_has(): void
    {
        MemberFixture::stored($this->members, 'beheer@example.org', 'Jan Bakker', 'Jan B.');

        $this->expectException(EmailAddressTaken::class);

        $this->handler()(new AddFirstAdmin(email: 'BEHEER@example.org', name: 'Bea Beheer', displayName: 'Bea'));
    }

    #[Test]
    public function it_records_the_added_admin(): void
    {
        $output = $this->handler()(new AddFirstAdmin(email: 'beheer@example.org', name: 'Bea Beheer', displayName: 'Bea'));

        $this->assertEquals(
            [new AuditedAction('member.added', 'member', Uuid::fromString($output->id), [
                'displayName' => ['old' => null, 'new' => 'Bea'],
                'roles' => ['old' => null, 'new' => 'admin'],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_adds_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new AddFirstAdmin(email: 'beheer@example.org', name: 'Bea Beheer', displayName: 'Bea'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame(
            [[['member.add_first_admin', null]], [], []],
            [$authorization->asked, $this->members->all(), $this->auditTrail->recorded],
        );
    }

    private function handler(?FakeAuthorization $authorization = null): AddFirstAdminHandler
    {
        return new AddFirstAdminHandler(
            $this->members,
            new FirstAdminGuard($this->members),
            new UniqueMemberDetails($this->members),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
