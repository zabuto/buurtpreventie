<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Mcp;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Support\McpTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class McpGuardsTest extends McpTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret of a second token
    private const string OTHER_SECRET = 'bpt_fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

    #[Test]
    public function it_refuses_the_61st_request_of_a_token_within_a_minute_and_says_when_to_retry(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        for ($request = 1; $request <= 60; ++$request) {
            self::post($browser);
            self::assertNotSame(429, $browser->getResponse()->getStatusCode(), \sprintf('Request %d was refused.', $request));
        }

        self::post($browser);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('urn:buurtpreventie:problem:too_many_requests', self::json($browser)['type']);
        self::assertGreaterThan(0, (int) $browser->getResponse()->headers->get('Retry-After'));
    }

    #[Test]
    public function it_counts_the_requests_of_each_token_apart(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        self::service(InMemoryApiTokenRepository::class)->add(ApiToken::issue(
            $jan->id(),
            new TokenTerms('Inspector', [TokenScope::Read], new DateTimeImmutable('2027-11-01T00:00:00+00:00')),
            self::OTHER_SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        ));
        for ($request = 1; $request <= 61; ++$request) {
            self::post($browser);
        }

        self::post($browser, self::OTHER_SECRET);

        self::assertNotSame(429, $browser->getResponse()->getStatusCode());
    }

    /**
     * A page on another site that resolves its own name to this server reaches it under that name: DNS rebinding.
     */
    #[Test]
    public function it_refuses_a_request_for_another_host(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $browser->request('POST', 'https://attacker.example/mcp', server: self::headers(), content: self::LIST_TOOLS);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_refuses_a_request_from_a_page_of_another_origin(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $browser->request('POST', self::ENDPOINT, server: [...self::headers(), 'HTTP_ORIGIN' => 'https://attacker.example'], content: self::LIST_TOOLS);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_answers_a_request_for_its_own_host_without_an_origin(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $browser->request('POST', self::ENDPOINT, server: self::headers(), content: self::LIST_TOOLS);

        self::assertResponseIsSuccessful();
    }

    private const string LIST_TOOLS = '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';

    private static function post(KernelBrowser $browser, #[SensitiveParameter] string $secret = self::SECRET): void
    {
        $browser->request('POST', self::ENDPOINT, server: self::headers($secret), content: self::LIST_TOOLS);
    }

    /**
     * @return array<string, string>
     */
    private static function headers(#[SensitiveParameter] string $secret = self::SECRET): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $secret,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD' => 'tools/list',
        ];
    }
}
