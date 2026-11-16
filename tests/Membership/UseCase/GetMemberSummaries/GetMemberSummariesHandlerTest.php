<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\GetMemberSummaries;

use App\Membership\UseCase\GetMemberSummaries\GetMemberSummaries;
use App\Membership\UseCase\GetMemberSummaries\GetMemberSummariesHandler;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GetMemberSummariesHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
    }

    /**
     * @return iterable<string, array{Role, bool}>
     */
    public static function rolesAndFullNames(): iterable
    {
        yield 'walker' => [Role::Walker, false];
        yield 'analyst' => [Role::Analyst, false];
        yield 'coordinator' => [Role::Coordinator, true];
        yield 'admin' => [Role::Admin, true];
    }

    #[DataProvider('rolesAndFullNames')]
    #[Test]
    public function it_shows_the_full_name_only_to_a_coordinator_or_admin_and_null_to_anyone_else(Role $role, bool $seesFullName): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $output = $this->handler(new Actor(Uuid::v7(), [$role]))(new GetMemberSummaries());

        $this->assertSame(
            [[
                'id' => $jan->id()->toRfc4122(),
                'displayName' => 'Jan B.',
                'name' => $seesFullName ? 'Jan Bakker' : null,
                'phone' => null,
                'address' => null,
            ]],
            $output->members,
        );
    }

    #[Test]
    public function it_finds_members_by_part_of_their_name_or_display_name(): void
    {
        MemberFixture::stored($this->members, 'jose@example.org', 'José Jansen', 'José J.');
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.');

        $output = $this->handler(new Actor(Uuid::v7(), [Role::Walker]))(new GetMemberSummaries(search: 'JAN'));

        $this->assertSame(['Jan B.', 'José J.'], \array_column($output->members, 'displayName'));
    }

    #[Test]
    public function it_leaves_erased_members_out(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $output = $this->handler(new Actor(Uuid::v7(), [Role::Admin]))(new GetMemberSummaries());

        $this->assertSame([], $output->members);
    }

    #[Test]
    public function it_leaves_deactivated_members_out(): void
    {
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.')->deactivate();

        $output = $this->handler(new Actor(Uuid::v7(), [Role::Coordinator]))(new GetMemberSummaries());

        $this->assertSame([], $output->members);
    }

    /**
     * Review focus 3: without consent no phone number or address, not even for a coordinator. The fields stay, as
     * null, so every member in the list has the same shape.
     */
    #[DataProvider('everyRole')]
    #[Test]
    public function it_shows_no_contact_details_of_a_member_without_consent(Role $role): void
    {
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $output = $this->handler(new Actor(Uuid::v7(), [$role]))(new GetMemberSummaries());

        $member = $output->members[0] ?? [];
        $this->assertSame([['phone' => null, 'address' => null], []], [
            \array_intersect_key($member, ['phone' => true, 'address' => true]),
            \array_intersect(['06 1234 5678', 'Dorpsstraat 1', 'jan@example.org'], \array_values($member)),
        ]);
    }

    #[Test]
    public function it_shows_null_for_contact_details_a_consenting_member_left_empty(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $jan->changeContactConsent(true);
        $jan->changeContactDetails(null, null);

        $output = $this->handler(new Actor(Uuid::v7(), [Role::Walker]))(new GetMemberSummaries());

        $member = $output->members[0] ?? [];
        $this->assertSame(['phone' => null, 'address' => null], \array_intersect_key($member, ['phone' => true, 'address' => true]));
    }

    /**
     * The e-mail address stays with the admin, who sees it under /admin/members.
     */
    #[DataProvider('everyRole')]
    #[Test]
    public function it_shows_phone_and_address_with_consent_but_never_the_email_address(Role $role): void
    {
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.')->changeContactConsent(true);

        $output = $this->handler(new Actor(Uuid::v7(), [$role]))(new GetMemberSummaries());

        $member = $output->members[0] ?? [];
        $this->assertSame(['06 1234 5678', 'Dorpsstraat 1', false], [
            $member['phone'] ?? null,
            $member['address'] ?? null,
            \in_array('jan@example.org', $member, strict: true),
        ]);
    }

    /**
     * @return iterable<string, array{Role}>
     */
    public static function everyRole(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    private function handler(Actor $actor): GetMemberSummariesHandler
    {
        return new GetMemberSummariesHandler($this->members, FakeAuthorization::granting(), new FixedExecutionContext($actor));
    }
}
