<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\RunAs;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use DomainException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class RunAsTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private RunAs $runAs;
    private TokenExecutionContext $context;

    #[Override]
    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->runAs = new RunAs($this->tokenStorage);
        $this->context = new TokenExecutionContext($this->tokenStorage);
    }

    #[Test]
    public function it_runs_the_operation_as_the_member_through_the_channel(): void
    {
        $actor = new Actor(Uuid::fromString('0193a3c0-0000-7000-8000-000000000001'), [Role::Walker, Role::Coordinator]);

        $seen = $this->runAs->member($actor, Channel::Console, fn(): array => [$this->context->actor(), $this->context->channel()]);

        $this->assertEquals([$actor, Channel::Console], $seen);
    }

    #[Test]
    public function it_runs_a_system_operation_without_a_member_through_cron(): void
    {
        $seen = $this->runAs->system(fn(): array => [
            $this->context->actor(),
            $this->context->channel(),
            $this->tokenStorage->getToken()?->getRoleNames(),
        ]);

        $this->assertSame([null, Channel::Cron, ['ROLE_SYSTEM']], $seen);
    }

    #[Test]
    public function it_puts_the_previous_token_back_afterwards(): void
    {
        $previous = new UsernamePasswordToken(new MemberUser(Uuid::v7(), []), 'api');
        $this->tokenStorage->setToken($previous);

        $this->runAs->system(static fn(): null => null);

        $this->assertSame($previous, $this->tokenStorage->getToken());
    }

    #[Test]
    public function it_puts_the_previous_token_back_when_the_operation_fails(): void
    {
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $this->runAs->member(new Actor(Uuid::v7(), []), Channel::Console, static fn(): never => throw $refusal);
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame([$refusal, null], [$caught, $this->tokenStorage->getToken()]);
    }
}
