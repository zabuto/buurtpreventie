<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\AddMember;

use App\Membership\Domain\DisplayNameTaken;
use App\Membership\Domain\EmailAddressTaken;
use App\Membership\Domain\UniqueMemberDetails;
use App\Membership\UseCase\AddMember\AddMember;
use App\Membership\UseCase\AddMember\AddMemberHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AddMemberHandlerTest extends TestCase
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
    public function it_adds_a_member_with_details_and_roles(): void
    {
        $output = $this->handler()(new AddMember(
            email: 'Jan@Example.org',
            name: 'Jan Bakker',
            displayName: 'Jan B.',
            phone: '06 1234 5678',
            address: 'Dorpsstraat 1',
            roles: ['walker', 'coordinator'],
        ));

        $member = $this->members->get(Uuid::fromString($output->id));
        $this->assertSame(
            ['jan@example.org', 'Jan Bakker', 'Jan B.', '06 1234 5678', 'Dorpsstraat 1', [Role::Walker, Role::Coordinator]],
            [$member->email()?->value, $member->name(), $member->displayName(), $member->phone()?->value, $member->address(), $member->roles()],
        );
    }

    #[Test]
    public function it_lets_two_members_share_a_full_name(): void
    {
        $handler = $this->handler();
        $handler(new AddMember(email: 'jan.bakker@example.org', name: 'Jan Bakker', displayName: 'Jan B.'));

        $handler(new AddMember(email: 'jan.b@example.org', name: 'Jan Bakker', displayName: 'Jan Ba.'));

        $this->assertCount(2, $this->members->all());
    }

    #[Test]
    public function it_lets_jose_and_jose_with_an_accent_exist_side_by_side(): void
    {
        $handler = $this->handler();
        $handler(new AddMember(email: 'jose@example.org', name: 'José', displayName: 'José J.'));

        $handler(new AddMember(email: 'jose.k@example.org', name: 'Jose', displayName: 'Jose K.'));

        $this->assertCount(2, $this->members->all());
    }

    #[Test]
    public function it_refuses_an_email_address_that_differs_only_in_case(): void
    {
        $handler = $this->handler();
        $handler(new AddMember(email: 'jan@example.org', name: 'Jan Bakker', displayName: 'Jan B.'));

        $this->expectException(EmailAddressTaken::class);

        $handler(new AddMember(email: 'JAN@Example.org', name: 'Jan Hendriks', displayName: 'Jan H.'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function collidingDisplayNames(): iterable
    {
        yield 'case and punctuation' => ['Jan H.', 'jan h'];
        yield 'accent' => ['José', 'Jose'];
    }

    #[DataProvider('collidingDisplayNames')]
    #[Test]
    public function it_refuses_a_display_name_that_collides_on_its_key(string $taken, string $requested): void
    {
        $handler = $this->handler();
        $handler(new AddMember(email: 'first@example.org', name: 'Eerste lid', displayName: $taken));

        $this->expectException(DisplayNameTaken::class);

        $handler(new AddMember(email: 'second@example.org', name: 'Tweede lid', displayName: $requested));
    }

    #[Test]
    public function it_records_the_added_member(): void
    {
        $output = $this->handler()(new AddMember(email: 'jan@example.org', name: 'Jan Bakker', displayName: 'Jan B.', roles: ['walker']));

        $this->assertEquals(
            [new AuditedAction('member.added', 'member', Uuid::fromString($output->id), [
                'displayName' => ['old' => null, 'new' => 'Jan B.'],
                'roles' => ['old' => null, 'new' => 'walker'],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_adds_nothing_when_access_is_denied(): void
    {
        $handler = $this->handler(FakeAuthorization::refusing());

        $refusal = null;
        try {
            $handler(new AddMember(email: 'jan@example.org', name: 'Jan Bakker', displayName: 'Jan B.'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([[], []], [$this->members->all(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): AddMemberHandler
    {
        return new AddMemberHandler(
            $this->members,
            new UniqueMemberDetails($this->members),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
