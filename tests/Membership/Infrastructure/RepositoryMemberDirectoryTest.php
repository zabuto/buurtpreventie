<?php

declare(strict_types=1);

namespace App\Tests\Membership\Infrastructure;

use App\Membership\Infrastructure\RepositoryMemberDirectory;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\MemberIdentity;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RepositoryMemberDirectoryTest extends TestCase
{
    private InMemoryMemberRepository $members;
    private RepositoryMemberDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->directory = new RepositoryMemberDirectory($this->members);
    }

    #[Test]
    public function it_knows_an_active_member_as_active(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $this->assertTrue($this->directory->isActive($jan->id()));
    }

    #[Test]
    public function it_knows_a_deactivated_member_as_inactive(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->deactivate();

        $this->assertFalse($this->directory->isActive($jan->id()));
    }

    #[Test]
    public function it_knows_an_unknown_member_as_inactive(): void
    {
        $this->assertFalse($this->directory->isActive(Uuid::v7()));
    }

    #[Test]
    public function it_lists_only_active_walkers_who_want_to_hear_about_open_rounds(): void
    {
        $wants = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $wants->changeOpenRoundNotifications(true);
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.');
        $inactive = MemberFixture::stored($this->members, 'kees@example.org', 'Kees Jansen', 'Kees J.');
        $inactive->changeOpenRoundNotifications(true);
        $inactive->deactivate();
        $coordinator = MemberFixture::stored($this->members, 'ria@example.org', 'Ria Smit', 'Ria S.', [Role::Coordinator]);
        $coordinator->changeOpenRoundNotifications(true);

        $subscribers = $this->directory->openRoundSubscribers();

        $this->assertEquals([$wants->id()], $subscribers);
    }

    #[Test]
    public function it_gives_the_names_and_the_phone_of_a_member_who_consents_to_sharing_it(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->changeContactConsent(true);

        $profiles = $this->directory->profilesOf([$jan->id()]);

        $this->assertEquals(
            [$jan->id()->toRfc4122() => new MemberProfile($jan->id(), 'Jan B.', 'Jan Bakker', '06 1234 5678', active: true)],
            $profiles,
        );
    }

    #[Test]
    public function it_withholds_the_phone_of_a_member_who_does_not_consent(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $profiles = $this->directory->profilesOf([$jan->id()]);

        $this->assertNull($profiles[$jan->id()->toRfc4122()]->phone);
    }

    #[Test]
    public function it_gives_an_erased_member_without_names_and_an_unknown_member_not_at_all(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $profiles = $this->directory->profilesOf([$jan->id(), Uuid::v7()]);

        $this->assertEquals(
            [$jan->id()->toRfc4122() => new MemberProfile($jan->id(), null, null, null, active: false)],
            $profiles,
        );
    }

    #[Test]
    public function it_identifies_a_member_by_id_with_the_roles_the_member_has_now(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.', [Role::Walker]);
        $jan->changeRoles([Role::Walker, Role::Coordinator]);

        $this->assertEquals(
            new MemberIdentity($jan->id(), [Role::Walker, Role::Coordinator], active: true),
            $this->directory->identity($jan->id()),
        );
    }

    #[Test]
    public function it_tells_whether_the_admin_picked_the_member_for_mcp(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.', [Role::Walker]);
        $jan->allowMcp(true);

        $this->assertEquals(
            new MemberIdentity($jan->id(), [Role::Walker], active: true, mcpAllowed: true),
            $this->directory->identity($jan->id()),
        );
    }

    #[Test]
    public function it_identifies_an_erased_member_as_inactive(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->erase(new DateTimeImmutable('2026-11-16T08:00:00+00:00'));

        $this->assertFalse($this->directory->identity($jan->id())?->active);
    }

    #[Test]
    public function it_identifies_nobody_for_an_unknown_id(): void
    {
        $this->assertNull($this->directory->identity(Uuid::v7()));
    }

    #[Test]
    public function it_identifies_a_member_by_e_mail_address_with_the_roles_the_member_has_now(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.', [Role::Walker]);
        $jan->changeRoles([Role::Analyst]);

        $this->assertEquals(
            new MemberIdentity($jan->id(), [Role::Analyst], active: true),
            $this->directory->identify(EmailAddress::fromString('Jan@Example.org')),
        );
    }

    #[Test]
    public function it_identifies_a_deactivated_member_by_e_mail_address_as_inactive(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->deactivate();

        $this->assertFalse($this->directory->identify(EmailAddress::fromString('jan@example.org'))?->active);
    }

    #[Test]
    public function it_identifies_nobody_for_an_unknown_e_mail_address(): void
    {
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $this->assertNull($this->directory->identify(EmailAddress::fromString('piet@example.org')));
    }

    #[Test]
    public function it_gives_the_address_mail_for_an_active_member_goes_to(): void
    {
        $jan = MemberFixture::stored($this->members, 'Jan@Example.org', 'Jan Bakker', 'Jan B.');

        $this->assertEquals(EmailAddress::fromString('jan@example.org'), $this->directory->emailOf($jan->id()));
    }

    #[Test]
    public function it_gives_no_address_for_a_deactivated_an_erased_or_an_unknown_member(): void
    {
        $deactivated = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $deactivated->deactivate();
        $erased = MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.');
        $erased->erase(new DateTimeImmutable('2026-11-16T10:00:00+00:00'));

        $addresses = [$this->directory->emailOf($deactivated->id()), $this->directory->emailOf($erased->id()), $this->directory->emailOf(Uuid::v7())];

        $this->assertSame([null, null, null], $addresses);
    }
}
