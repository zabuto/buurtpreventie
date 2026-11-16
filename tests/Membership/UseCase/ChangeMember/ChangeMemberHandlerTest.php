<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ChangeMember;

use App\Membership\Domain\DisplayNameTaken;
use App\Membership\Domain\EmailAddressTaken;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberErased;
use App\Membership\Domain\UniqueMemberDetails;
use App\Membership\UseCase\ChangeMember\ChangeMember;
use App\Membership\UseCase\ChangeMember\ChangeMemberHandler;
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

final class ChangeMemberHandlerTest extends TestCase
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
    public function it_changes_the_details_and_the_certificate(): void
    {
        $this->handler()(new ChangeMember(
            memberId: $this->jan->id()->toRfc4122(),
            email: 'jan.bakker@example.org',
            name: 'Jan Bakker-de Vries',
            displayName: 'Jan BdV',
            phone: '06 8765 4321',
            address: 'Kerkstraat 2',
            certified: true,
        ));

        $this->assertSame(
            ['jan.bakker@example.org', 'Jan Bakker-de Vries', 'Jan BdV', '06 8765 4321', 'Kerkstraat 2', true],
            [
                $this->jan->email()?->value,
                $this->jan->name(),
                $this->jan->displayName(),
                $this->jan->phone()?->value,
                $this->jan->address(),
                $this->jan->isCertified(),
            ],
        );
    }

    #[Test]
    public function it_keeps_the_details_left_out(): void
    {
        $this->handler()(new ChangeMember(memberId: $this->jan->id()->toRfc4122(), displayName: 'Jan Ba.'));

        $this->assertSame(
            ['jan@example.org', 'Jan Bakker', 'Jan Ba.', '06 1234 5678', 'Dorpsstraat 1', false],
            [
                $this->jan->email()?->value,
                $this->jan->name(),
                $this->jan->displayName(),
                $this->jan->phone()?->value,
                $this->jan->address(),
                $this->jan->isCertified(),
            ],
        );
    }

    #[Test]
    public function it_clears_the_phone_number_and_address_given_as_null(): void
    {
        $this->handler()(new ChangeMember(memberId: $this->jan->id()->toRfc4122(), phone: null, address: null));

        $this->assertSame([null, null], [$this->jan->phone(), $this->jan->address()]);
    }

    #[Test]
    public function it_refuses_an_erased_member_even_without_new_details(): void
    {
        $this->jan->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $this->expectException(MemberErased::class);

        $this->handler()(new ChangeMember(memberId: $this->jan->id()->toRfc4122()));
    }

    #[Test]
    public function it_keeps_the_members_own_address_and_display_name_available(): void
    {
        $this->handler()($this->change(email: 'JAN@example.org', displayName: 'jan b'));

        $this->assertSame(['jan@example.org', 'jan b'], [$this->jan->email()?->value, $this->jan->displayName()]);
    }

    #[Test]
    public function it_refuses_an_address_of_another_member_that_differs_only_in_case(): void
    {
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet Jansen', 'Piet J.');

        $this->expectException(EmailAddressTaken::class);

        $this->handler()($this->change(email: 'Piet@Example.org'));
    }

    #[Test]
    public function it_refuses_a_display_name_that_collides_with_another_member(): void
    {
        MemberFixture::stored($this->members, 'jan.h@example.org', 'Jan Hendriks', 'Jan H.');

        $this->expectException(DisplayNameTaken::class);

        $this->handler()($this->change(displayName: 'jan h'));
    }

    #[Test]
    public function it_records_which_details_changed_without_contact_values(): void
    {
        $this->handler()($this->change(displayName: 'Jan Ba.', phone: '06 8765 4321'));

        $this->assertEquals(
            [new AuditedAction('member.changed', 'member', $this->jan->id(), [
                'displayName' => ['old' => 'Jan B.', 'new' => 'Jan Ba.'],
                'phone' => ['old' => null, 'new' => null],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_when_nothing_changed(): void
    {
        $this->handler()($this->change());

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)($this->change(displayName: 'Jan Ba.'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.change', $this->jan]], $authorization->asked);
        $this->assertSame(['Jan B.', []], [$this->jan->displayName(), $this->auditTrail->recorded]);
    }

    private function change(string $email = 'jan@example.org', string $displayName = 'Jan B.', string $phone = '06 1234 5678'): ChangeMember
    {
        return new ChangeMember(
            memberId: $this->jan->id()->toRfc4122(),
            email: $email,
            name: 'Jan Bakker',
            displayName: $displayName,
            phone: $phone,
            address: 'Dorpsstraat 1',
            certified: false,
        );
    }

    private function handler(?FakeAuthorization $authorization = null): ChangeMemberHandler
    {
        return new ChangeMemberHandler(
            $this->members,
            new UniqueMemberDetails($this->members),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
