<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ListMembers;

use App\Membership\UseCase\ListMembers\ListMembers;
use App\Membership\UseCase\ListMembers\ListMembersHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListMembersHandlerTest extends TestCase
{
    private InMemoryMemberRepository $members;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
    }

    #[Test]
    public function it_lists_every_member_with_everything_the_admin_manages(): void
    {
        $jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.', [Role::Walker, Role::Coordinator]);

        $output = $this->handler()(new ListMembers());

        $this->assertSame(
            [[
                'id' => $jan->id()->toRfc4122(),
                'email' => 'jan@example.org',
                'name' => 'Jan Bakker',
                'displayName' => 'Jan B.',
                'phone' => '06 1234 5678',
                'address' => 'Dorpsstraat 1',
                'roles' => ['walker', 'coordinator'],
                'active' => true,
                'certified' => false,
                'consentContactShare' => false,
                'notifyOpenRounds' => false,
            ]],
            $output->members,
        );
    }

    #[Test]
    public function it_lists_deactivated_members_too_but_not_erased_ones(): void
    {
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.')->deactivate();
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.')->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $output = $this->handler()(new ListMembers());

        $this->assertSame([['Jan B.', false]], \array_map(
            static fn(array $member): array => [$member['displayName'], $member['active']],
            $output->members,
        ));
    }

    #[Test]
    public function it_finds_members_by_part_of_their_name(): void
    {
        MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.');

        $output = $this->handler()(new ListMembers(search: 'vries'));

        $this->assertSame(['Piet V.'], \array_column($output->members, 'displayName'));
    }

    #[Test]
    public function it_refuses_whoever_may_not_view_every_member(): void
    {
        $authorization = FakeAuthorization::refusing();

        try {
            $this->handler($authorization)(new ListMembers());
            $this->fail('The list was given.');
        } catch (AccessDenied) {
            $this->assertSame([['member.view', null]], $authorization->asked);
        }
    }

    private function handler(?FakeAuthorization $authorization = null): ListMembersHandler
    {
        return new ListMembersHandler($this->members, $authorization ?? FakeAuthorization::granting());
    }
}
