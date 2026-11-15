<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Channel;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\RunAs;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * RunAs, the execution context and the authorization as the container wires them. Each must read the one token
 * storage RunAs writes to; with a second one, a handler would record one actor and the voters would judge another.
 */
#[Group('integration')]
final class RunAsWiringTest extends KernelTestCase
{
    private RunAs $runAs;
    private ExecutionContext $context;
    private Authorization $authorization;

    #[Override]
    protected function setUp(): void
    {
        $container = self::getContainer();
        $runAs = $container->get(RunAs::class);
        $context = $container->get(ExecutionContext::class);
        $authorization = $container->get(Authorization::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(RunAs::class, $runAs);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(ExecutionContext::class, $context);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(Authorization::class, $authorization);
        $this->runAs = $runAs;
        $this->context = $context;
        $this->authorization = $authorization;
    }

    #[Test]
    public function the_system_acts_through_its_channel_with_its_own_rights(): void
    {
        $seen = $this->runAs->system(Channel::Console, fn(): array => [
            $this->context->actor(),
            $this->context->channel(),
            $this->granted('member.add_first_admin'),
            $this->granted('member.add'),
        ]);

        $this->assertSame([null, Channel::Console, true, false], $seen);
    }

    #[Test]
    public function a_member_acts_through_the_channel_with_the_rights_of_their_roles(): void
    {
        $admin = new Actor(Uuid::fromString('0193a3c0-0000-7000-8000-000000000001'), [Role::Admin]);

        $seen = $this->runAs->member($admin, Channel::Web, fn(): array => [
            $this->context->actor(),
            $this->context->channel(),
            $this->granted('member.add'),
            $this->granted('member.add_first_admin'),
        ]);

        $this->assertEquals([$admin, Channel::Web, true, false], $seen);
    }

    private function granted(string $action): bool
    {
        try {
            $this->authorization->denyUnlessGranted($action);
        } catch (AccessDenied) {
            return false;
        }

        return true;
    }
}
