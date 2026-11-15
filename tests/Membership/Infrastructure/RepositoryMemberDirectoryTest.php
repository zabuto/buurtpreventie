<?php

declare(strict_types=1);

namespace App\Tests\Membership\Infrastructure;

use App\Membership\Infrastructure\RepositoryMemberDirectory;
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
}
