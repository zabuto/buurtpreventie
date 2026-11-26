<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The limits per token hold on REST and MCP alike, so a token stopped on one path cannot carry on along the other.
 */
#[Group('functional')]
final class TokenRateLimitTest extends McpTestCase
{
    private const string READ = '/api/v1/outcomes';

    #[Test]
    public function it_refuses_the_61st_rest_read_of_a_token_within_a_minute_and_says_when_to_retry(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        for ($request = 1; $request <= 60; ++$request) {
            self::restRead($browser);
            self::assertNotSame(429, $browser->getResponse()->getStatusCode(), \sprintf('Read %d was refused.', $request));
        }

        self::restRead($browser);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('urn:buurtpreventie:problem:too_many_requests', self::json($browser)['type']);
        self::assertGreaterThan(0, (int) $browser->getResponse()->headers->get('Retry-After'));
    }

    #[Test]
    public function it_counts_mcp_and_rest_reads_of_a_token_toward_the_same_limit(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        for ($request = 1; $request <= 30; ++$request) {
            self::mcpRead($browser);
            self::restRead($browser);
        }

        self::restRead($browser);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function it_leaves_a_browser_session_out_of_the_limits(): void
    {
        $browser = self::createClient();
        $browser->disableReboot();
        self::actingAs($browser);
        for ($request = 1; $request <= 60; ++$request) {
            $browser->request('GET', self::READ);
        }

        $browser->request('GET', self::READ);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function it_refuses_the_21st_rest_write_of_a_token_within_an_hour_and_says_when_to_retry(): void
    {
        $browser = self::mcpBrowser();
        $round = self::round('2026-11-20T19:00:00+00:00', self::walker('Piet K.'));
        self::token(self::member());
        for ($request = 1; $request <= 20; ++$request) {
            self::restSignUp($browser, $round->id()->toRfc4122());
            self::assertResponseIsSuccessful(\sprintf('Write %d was refused.', $request));
        }

        self::restSignUp($browser, $round->id()->toRfc4122());

        self::assertResponseStatusCodeSame(429);
        self::assertGreaterThan(0, (int) $browser->getResponse()->headers->get('Retry-After'));
    }

    #[Test]
    public function it_counts_a_writing_tool_call_toward_the_write_limit_of_rest(): void
    {
        $browser = self::mcpBrowser();
        $roundId = self::round('2026-11-20T19:00:00+00:00', self::walker('Piet K.'))->id()->toRfc4122();
        self::token(self::member());
        $client = self::connect($browser);
        for ($call = 1; $call <= 20; ++$call) {
            self::call($client, 'sign_up_for_round', ['roundId' => $roundId]);
        }

        self::restSignUp($browser, $roundId);

        self::assertResponseStatusCodeSame(429);
    }

    /**
     * The tool runs inside the answer of the server, so the refusal is a tool error, not a 429, and carries the
     * seconds of the Retry-After header that an MCP result has no place for.
     */
    #[Test]
    public function it_refuses_a_writing_tool_call_above_the_write_limit_and_says_when_to_retry(): void
    {
        $browser = self::mcpBrowser();
        $roundId = self::round('2026-11-20T19:00:00+00:00', self::walker('Piet K.'))->id()->toRfc4122();
        self::token(self::member());
        for ($request = 1; $request <= 20; ++$request) {
            self::restSignUp($browser, $roundId);
        }

        $problem = self::failure(self::connect($browser), 'sign_up_for_round', ['roundId' => $roundId]);

        self::assertSame(429, $problem['status']);
        self::assertSame('urn:buurtpreventie:problem:too_many_requests', $problem['type']);
        self::assertIsInt($problem['retryAfter'] ?? null);
        self::assertGreaterThan(0, $problem['retryAfter']);
    }

    private static function restSignUp(KernelBrowser $browser, string $roundId): void
    {
        $browser->request('PUT', '/api/v1/rounds/' . $roundId . '/walkers/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);
    }

    private const string LIST_TOOLS = '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';

    private static function mcpRead(KernelBrowser $browser): void
    {
        $browser->request(
            'POST',
            self::ENDPOINT,
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json, text/event-stream',
                'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
                'HTTP_MCP_METHOD' => 'tools/list',
            ],
            content: self::LIST_TOOLS,
        );
    }

    private static function restRead(KernelBrowser $browser): void
    {
        $browser->request('GET', self::READ, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);
    }
}
