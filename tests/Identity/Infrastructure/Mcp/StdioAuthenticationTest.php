<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Mcp;

use App\Identity\Infrastructure\Mcp\StdioAuthentication;
use App\Identity\Infrastructure\Security\ApiTokenAuthenticator;
use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetMcpAccess\GetMcpAccessHandler;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\McpTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

#[Group('functional')]
final class StdioAuthenticationTest extends McpTestCase
{
    #[Test]
    public function it_runs_the_stdio_server_as_the_member_of_the_token_on_the_mcp_channel(): void
    {
        self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        $storage = new TokenStorage();

        self::authentication($storage, self::SECRET)(self::start('mcp:server'));

        $token = $storage->getToken();
        self::assertNotNull($token);
        self::assertSame($jan->id()->toRfc4122(), $token->getUserIdentifier());
        self::assertSame(Channel::Mcp, $token->getAttribute(TokenExecutionContext::CHANNEL));
        self::assertNotSame([], ApiTokenAuthenticator::scopesOf($token));
    }

    #[Test]
    public function it_does_not_start_while_mcp_is_off(): void
    {
        self::mcpBrowser();
        self::token(self::member());
        self::service(InMemorySettingRepository::class)->add(Setting::of('integrations.mcp', '0'));

        $this->expectExceptionObject(new RuntimeException('MCP is off for this association; an admin switches it on in the settings (mcpAccess).'));

        self::authentication(new TokenStorage(), self::SECRET)(self::start('mcp:server'));
    }

    #[Test]
    public function it_does_not_start_without_a_token(): void
    {
        self::mcpBrowser();

        $this->expectException(RuntimeException::class);

        self::authentication(new TokenStorage(), null)(self::start('mcp:server'));
    }

    #[Test]
    public function it_does_not_start_with_a_revoked_token(): void
    {
        self::mcpBrowser();
        self::token(self::member())->revoke(new DateTimeImmutable('2026-11-15T10:00:00+00:00'));
        $storage = new TokenStorage();

        $refusal = null;
        try {
            self::authentication($storage, self::SECRET)(self::start('mcp:server'));
        } catch (RuntimeException $exception) {
            $refusal = $exception;
        }

        self::assertNotNull($refusal);
        self::assertNull($storage->getToken());
    }

    #[Test]
    public function it_leaves_every_other_command_alone(): void
    {
        self::mcpBrowser();
        $storage = new TokenStorage();

        self::authentication($storage, null)(self::start('cache:clear'));

        self::assertNull($storage->getToken());
    }

    private static function authentication(TokenStorage $storage, #[SensitiveParameter] ?string $secret): StdioAuthentication
    {
        return new StdioAuthentication(
            self::service(GetMcpAccessHandler::class),
            self::service(ApiTokenAuthenticator::class),
            $storage,
            $secret,
        );
    }

    private static function start(string $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent(new Command($command), new ArrayInput([]), new NullOutput());
    }
}
