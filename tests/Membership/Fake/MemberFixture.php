<?php

declare(strict_types=1);

namespace App\Tests\Membership\Fake;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\PhoneNumber;
use App\Shared\Domain\Role;

final readonly class MemberFixture
{
    /**
     * @param list<Role> $roles
     */
    public static function stored(
        InMemoryMemberRepository $members,
        string $email,
        string $name,
        string $displayName,
        array $roles = [Role::Walker],
    ): Member {
        $member = Member::register(new MemberDetails(
            EmailAddress::fromString($email),
            $name,
            $displayName,
            PhoneNumber::fromString('06 1234 5678'),
            'Dorpsstraat 1',
        ));
        $member->changeRoles($roles);
        $members->add($member);

        return $member;
    }
}
