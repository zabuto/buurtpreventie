<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\AccessDenied;
use App\Shared\Infrastructure\Security\SymfonyAuthorization;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

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
}
