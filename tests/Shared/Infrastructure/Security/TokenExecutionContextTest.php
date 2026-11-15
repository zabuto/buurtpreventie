<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class TokenExecutionContextTest extends TestCase
{
    #[Test]
    public function it_takes_the_actor_and_channel_from_the_token_an_authenticator_set(): void
    {
        $memberId = Uuid::fromString('0193a3c0-0000-7000-8000-000000000002');
        $token = new UsernamePasswordToken(new MemberUser($memberId, [Role::Analyst]), 'api');
        $token->setAttribute(TokenExecutionContext::CHANNEL, Channel::Api);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $context = new TokenExecutionContext($tokenStorage);

        $seen = [$context->actor(), $context->channel()];

        $this->assertEquals([new Actor($memberId, [Role::Analyst]), Channel::Api], $seen);
    }

    #[Test]
    public function it_has_no_actor_without_a_token(): void
    {
        $context = new TokenExecutionContext(new TokenStorage());

        $actor = $context->actor();

        $this->assertNull($actor);
    }

    /**
     * An entry point that forgets the channel fails loudly instead of recording a wrong channel in the audit log.
     */
    #[Test]
    public function it_refuses_to_guess_a_channel_no_entry_point_set(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new MemberUser(Uuid::v7(), []), 'api'));
        $context = new TokenExecutionContext($tokenStorage);

        $this->expectException(LogicException::class);

        $context->channel();
    }
}
