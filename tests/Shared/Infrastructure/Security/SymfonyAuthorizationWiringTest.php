<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\SymfonyAuthorization;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Runs against the configured access decision strategy: an action without a voter that decides must be refused,
 * so a forgotten voter locks an action instead of opening it.
 */
#[Group('integration')]
final class SymfonyAuthorizationWiringTest extends KernelTestCase
{
    #[Test]
    public function it_refuses_an_action_no_voter_decides_on_when_nobody_is_signed_in(): void
    {
        $checker = self::getContainer()->get(AuthorizationCheckerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(AuthorizationCheckerInterface::class, $checker);
        $authorization = new SymfonyAuthorization($checker);

        $this->expectException(AccessDenied::class);

        $authorization->denyUnlessGranted('round.change');
    }

    /**
     * The voters are found by autoconfiguration; one that is not tagged would leave its actions refused for everyone.
     */
    #[Test]
    public function it_lets_the_voters_of_the_modules_decide_for_the_member_on_the_token(): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $checker = self::getContainer()->get(AuthorizationCheckerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(AuthorizationCheckerInterface::class, $checker);
        $admin = new MemberUser(Uuid::v7(), [Role::Admin]);
        $tokenStorage->setToken(new UsernamePasswordToken($admin, 'test', $admin->getRoles()));

        $decisions = [$checker->isGranted('catalogue.manage'), $checker->isGranted('member.add'), $checker->isGranted('round.send_reminders')];

        $this->assertSame([true, true, false], $decisions);
    }
}
