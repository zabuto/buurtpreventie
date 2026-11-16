<?php

declare(strict_types=1);

namespace App\Tests\Membership\Domain;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberErased;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\PhoneNumber;
use App\Shared\Domain\Role;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    #[Test]
    public function it_keys_its_name_and_display_name(): void
    {
        $member = $this->member();

        $this->assertSame(['josé jansen', 'jose jansen', 'José J.', 'jose j'], [
            \mb_strtolower((string) $member->name()),
            $member->nameKey(),
            $member->displayName(),
            $member->displayNameKey(),
        ]);
    }

    #[Test]
    public function it_starts_active_without_consent_to_share_contact_details(): void
    {
        $member = $this->member();

        $this->assertSame([true, false, false], [$member->isActive(), $member->consentsToContactShare(), $member->isErased()]);
    }

    #[Test]
    public function it_keeps_each_role_once(): void
    {
        $member = $this->member();

        $member->changeRoles([Role::Walker, Role::Coordinator, Role::Walker]);

        $this->assertSame([Role::Walker, Role::Coordinator], $member->roles());
    }

    #[Test]
    public function it_takes_new_details_and_their_keys(): void
    {
        $member = $this->member();

        $member->change(new MemberDetails(EmailAddress::fromString('jan@example.org'), 'Jan Bakker', 'Jan B.'));

        $this->assertSame(['jan@example.org', 'jan bakker', 'jan b'], [$member->email()?->value, $member->nameKey(), $member->displayNameKey()]);
    }

    #[Test]
    public function it_erases_contact_details_and_the_name(): void
    {
        $member = $this->member();
        $member->changeRoles([Role::Walker]);
        $member->changeContactConsent(true);

        $member->erase(new DateTimeImmutable(self::NOW));

        $this->assertSame(
            [null, null, null, null, null, null, null, [], false, false, false],
            [
                $member->email(),
                $member->name(),
                $member->nameKey(),
                $member->displayName(),
                $member->displayNameKey(),
                $member->phone(),
                $member->address(),
                $member->roles(),
                $member->consentsToContactShare(),
                $member->wantsOpenRoundNotifications(),
                $member->isActive(),
            ],
        );
        $this->assertEquals(new DateTimeImmutable(self::NOW), $member->erasedAt());
    }

    #[Test]
    public function it_refuses_new_details_once_erased(): void
    {
        $member = $this->member();
        $member->erase(new DateTimeImmutable(self::NOW));

        $this->expectException(MemberErased::class);

        $member->change(new MemberDetails(EmailAddress::fromString('jose@example.org'), 'José Jansen', 'José J.'));
    }

    #[Test]
    public function it_refuses_to_reactivate_an_erased_member(): void
    {
        $member = $this->member();
        $member->erase(new DateTimeImmutable(self::NOW));

        $this->expectException(MemberErased::class);

        $member->reactivate();
    }

    /**
     * An erased member gets no mail at all; a notification setting would make it a recipient.
     */
    #[Test]
    public function it_refuses_open_round_notifications_once_erased(): void
    {
        $member = $this->member();
        $member->erase(new DateTimeImmutable(self::NOW));

        $this->expectException(MemberErased::class);

        $member->changeOpenRoundNotifications(true);
    }

    #[Test]
    public function it_takes_a_new_phone_number_and_address_and_keeps_its_other_details(): void
    {
        $member = $this->member();

        $member->changeContactDetails(PhoneNumber::fromString('020 123 4567'), '  Kerkstraat 2 ');

        $this->assertSame(['020 123 4567', 'Kerkstraat 2', 'jose@example.org', 'José J.'], [
            $member->phone()?->value,
            $member->address(),
            $member->email()?->value,
            $member->displayName(),
        ]);
    }

    #[Test]
    public function it_drops_an_address_of_only_whitespace(): void
    {
        $member = $this->member();

        $member->changeContactDetails(null, '   ');

        $this->assertSame([null, null], [$member->phone(), $member->address()]);
    }

    #[Test]
    public function it_refuses_new_contact_details_once_erased(): void
    {
        $member = $this->member();
        $member->erase(new DateTimeImmutable(self::NOW));

        $this->expectException(MemberErased::class);

        $member->changeContactDetails(null, 'Kerkstraat 2');
    }

    #[Test]
    public function it_reactivates_a_deactivated_member(): void
    {
        $member = $this->member();
        $member->deactivate();

        $member->reactivate();

        $this->assertTrue($member->isActive());
    }

    private function member(): Member
    {
        return Member::register(new MemberDetails(
            email: EmailAddress::fromString('Jose@Example.org'),
            name: 'José Jansen',
            displayName: 'José J.',
            phone: PhoneNumber::fromString('06 1234 5678'),
            address: 'Dorpsstraat 1',
        ));
    }
}
