<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Mcp;

use App\Settings\Domain\Setting;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\McpTestCase;
use DateTimeImmutable;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class McpAccessTest extends McpTestCase
{
    /**
     * @return iterable<string, array{ProtocolVersion}>
     */
    public static function revisions(): iterable
    {
        yield '2026-07-28, without a session' => [ProtocolVersion::V2026_07_28];
        yield '2025-11-25, with the handshake' => [ProtocolVersion::V2025_11_25];
        yield '2025-06-18, with the handshake' => [ProtocolVersion::V2025_06_18];
    }

    #[DataProvider('revisions')]
    #[Test]
    public function it_serves_a_client_of_either_protocol_era(ProtocolVersion $version): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $client = self::connect($browser, $version);

        self::assertSame($version, $client->getProtocolVersion());
        self::assertContains('staffing_gaps', \array_map(static fn(Tool $tool): string => $tool->name, $client->listTools()->tools));
        self::assertSame(['gaps' => []], self::call($client, 'staffing_gaps'));
    }

    #[Test]
    public function it_refuses_a_request_without_a_token_with_an_empty_401(): void
    {
        $browser = self::mcpBrowser();

        $browser->request('POST', self::ENDPOINT, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
    }

    /**
     * A session cookie would let another site, or a page after DNS rebinding, act as the member on the MCP server.
     */
    #[Test]
    public function it_refuses_a_browser_session_without_a_token(): void
    {
        $browser = self::mcpBrowser();
        $member = self::member();
        $browser->loginUser(new MemberUser($member->id(), $member->roles()), 'browser', [TokenExecutionContext::CHANNEL => Channel::Web]);

        $browser->request('POST', self::ENDPOINT, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_refuses_a_revoked_token(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member())->revoke(new DateTimeImmutable('2026-11-15T10:00:00+00:00'));

        $browser->request('POST', self::ENDPOINT, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_refuses_every_token_while_api_tokens_are_off_even_with_mcp_on(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        self::service(InMemorySettingRepository::class)->add(Setting::of('integrations.api_access', '0'));

        $browser->request('POST', self::ENDPOINT, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        self::assertResponseStatusCodeSame(401);
    }
}
