<?php

declare(strict_types=1);

namespace App\Tests\Membership\Domain;

use App\Membership\Domain\MemberPolicy;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MemberPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{list<Role>, bool}>
     */
    public static function managers(): iterable
    {
        yield 'an admin' => [[Role::Admin], true];
        yield 'a coordinator' => [[Role::Coordinator], false];
        yield 'a walker' => [[Role::Walker], false];
        yield 'an analyst' => [[Role::Analyst], false];
        yield 'a member without a role' => [[], false];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('managers')]
    #[Test]
    public function it_lets_only_an_admin_manage_members(array $roles, bool $expected): void
    {
        $this->assertSame($expected, new MemberPolicy()->mayManage(new Actor(Uuid::v7(), $roles)));
    }

    #[Test]
    public function it_lets_a_member_make_their_own_choice(): void
    {
        $member = MemberFixture::stored(new InMemoryMemberRepository(), 'jan@example.org', 'Jan Bakker', 'Jan B.', []);

        $this->assertTrue(new MemberPolicy()->mayChangeOwnChoice(new Actor($member->id(), []), $member));
    }

    /**
     * Consent and notifications are the member's own choice; not even an admin makes it for them.
     */
    #[Test]
    public function it_refuses_an_admin_the_choice_of_another_member(): void
    {
        $member = MemberFixture::stored(new InMemoryMemberRepository(), 'jan@example.org', 'Jan Bakker', 'Jan B.');

        $this->assertFalse(new MemberPolicy()->mayChangeOwnChoice(new Actor(Uuid::v7(), [Role::Admin]), $member));
    }
}
