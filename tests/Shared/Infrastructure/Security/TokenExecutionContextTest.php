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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
        $context = new TokenExecutionContext($tokenStorage, new RequestStack());

        $seen = [$context->actor(), $context->channel()];

        $this->assertEquals([new Actor($memberId, [Role::Analyst]), Channel::Api], $seen);
    }

    #[Test]
    public function it_has_no_actor_without_a_token(): void
    {
        $context = new TokenExecutionContext(new TokenStorage(), new RequestStack());

        $actor = $context->actor();

        $this->assertNull($actor);
    }

    /**
     * Nobody logged in yet, such as a member asking for a login code: a request with an API token never gets this
     * far without a token, so a request without one came through the browser.
     */
    #[Test]
    public function it_runs_an_http_request_without_a_token_on_the_web_channel_without_an_actor(): void
    {
        $requests = new RequestStack([Request::create('/api/v1/auth/login-code', 'POST')]);
        $context = new TokenExecutionContext(new TokenStorage(), $requests);

        $seen = [$context->actor(), $context->channel()];

        $this->assertSame([null, Channel::Web], $seen);
    }

    #[Test]
    public function it_refuses_to_guess_a_channel_without_a_token_outside_a_request(): void
    {
        $context = new TokenExecutionContext(new TokenStorage(), new RequestStack());

        $this->expectException(LogicException::class);

        $context->channel();
    }

    /**
     * An entry point that forgets the channel fails loudly instead of recording a wrong channel in the audit log.
     */
    #[Test]
    public function it_refuses_to_guess_a_channel_no_entry_point_set(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new MemberUser(Uuid::v7(), []), 'api'));
        $requests = new RequestStack([Request::create('/api/v1/rounds')]);
        $context = new TokenExecutionContext($tokenStorage, $requests);

        $this->expectException(LogicException::class);

        $context->channel();
    }
}
