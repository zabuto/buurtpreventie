<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MemberUserTest extends TestCase
{
    #[Test]
    public function it_hands_symfony_one_role_name_per_role_and_the_member_id_as_identifier(): void
    {
        $user = new MemberUser(Uuid::fromString('0193a3c0-0000-7000-8000-000000000003'), [Role::Walker, Role::Admin]);

        $seen = [$user->getRoles(), $user->getUserIdentifier()];

        $this->assertSame([['ROLE_WALKER', 'ROLE_ADMIN'], '0193a3c0-0000-7000-8000-000000000003'], $seen);
    }

    /**
     * Symfony keeps a session while the refreshed user is the same user: a changed role keeps it, a new password
     * ends it.
     *
     * @return iterable<string, array{MemberUser, bool}>
     */
    public static function refreshedUsers(): iterable
    {
        $memberId = Uuid::fromString('0193a3c0-0000-7000-8000-000000000003');

        yield 'same member, other roles' => [new MemberUser($memberId, [Role::Coordinator], '1763287200.000000'), true];
        yield 'same member, new password' => [new MemberUser($memberId, [Role::Walker], '1763290800.000000'), false];
        yield 'another member' => [new MemberUser(Uuid::fromString('0193a3c0-0000-7000-8000-000000000004'), [Role::Walker], '1763287200.000000'), false];
    }

    #[DataProvider('refreshedUsers')]
    #[Test]
    public function it_is_the_same_user_while_member_and_credentials_are(MemberUser $refreshed, bool $same): void
    {
        $user = new MemberUser(Uuid::fromString('0193a3c0-0000-7000-8000-000000000003'), [Role::Walker], '1763287200.000000');

        $this->assertSame($same, $user->isEqualTo($refreshed));
    }
}
