<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
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
}
