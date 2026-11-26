<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Mcp;

use App\Settings\Domain\Setting;
use App\Shared\Domain\Role;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * On the MCP server a member keeps only the roles the association allows there (mcpRoles); REST keeps every role.
 */
#[Group('functional')]
final class McpRolesTest extends McpTestCase
{
    private const string LIST_TOOLS = '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';

    /**
     * Allowing analysts only must not hand the write tools to an analyst who also walks.
     */
    #[Test]
    public function it_gives_a_member_only_the_allowed_roles_on_mcp(): void
    {
        $browser = self::mcpBrowser();
        self::allowOnMcp('analyst');
        $roundId = self::round('2026-11-28T19:00:00+00:00', self::walker('Piet K.'))->id()->toRfc4122();
        self::token(self::member(Role::Walker, Role::Analyst));
        $client = self::connect($browser);

        $statistics = self::call($client, 'round_statistics', ['from' => '2026-11-01T00:00:00+01:00', 'to' => '2026-12-01T00:00:00+01:00']);
        $refusal = self::failure($client, 'sign_up_for_round', ['roundId' => $roundId]);

        self::assertSame('month', $statistics['groupBy']);
        self::assertSame([403, 'urn:buurtpreventie:problem:access_denied'], [$refusal['status'], $refusal['type']]);
    }

    /**
     * A 404 would say MCP is off, while it is on; the member has to learn why it does not work.
     */
    #[Test]
    public function it_refuses_a_member_without_an_allowed_role_with_a_problem_that_says_so(): void
    {
        $browser = self::mcpBrowser();
        self::allowOnMcp('analyst', 'coordinator');
        self::token(self::member(Role::Walker));

        self::listTools($browser);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:mcp_not_for_role', self::json($browser)['type']);
    }

    /**
     * The firewall and the router decode the path; an encoded `/mcp` must not reach the server with every role.
     */
    #[Test]
    public function it_refuses_a_member_without_an_allowed_role_on_a_percent_encoded_path_too(): void
    {
        $browser = self::mcpBrowser();
        self::allowOnMcp('analyst');
        self::token(self::member(Role::Walker));

        self::listTools($browser, '/%6Dcp');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:mcp_not_for_role', self::json($browser)['type']);
    }

    /**
     * The refusal comes before the limits, so a client that keeps trying does not use up the token on REST.
     */
    #[Test]
    public function it_counts_no_refused_request_toward_the_limits_of_the_token(): void
    {
        $browser = self::mcpBrowser();
        self::allowOnMcp('analyst');
        self::token(self::member(Role::Walker));
        for ($request = 1; $request <= 61; ++$request) {
            self::listTools($browser);
        }

        $browser->request('GET', '/api/v1/outcomes', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        self::assertResponseIsSuccessful();
    }

    /**
     * A member the admin picks keeps every role on MCP, whichever roles the association allows.
     */
    #[Test]
    public function it_gives_a_picked_member_every_role_on_mcp(): void
    {
        $browser = self::mcpBrowser();
        self::allowOnMcp('analyst');
        $roundId = self::round('2026-11-28T19:00:00+00:00', self::walker('Piet K.'))->id()->toRfc4122();
        $member = self::member(Role::Walker);
        $member->allowMcp(true);
        self::token($member);

        $answer = self::call(self::connect($browser), 'sign_up_for_round', ['roundId' => $roundId]);

        self::assertTrue($answer['signedUp'] ?? null);
    }

    #[Test]
    public function it_lets_a_picked_member_in_when_no_role_may_use_mcp(): void
    {
        $browser = self::mcpBrowser();
        self::allowOnMcp();
        $member = self::member(Role::Walker);
        $member->allowMcp(true);
        self::token($member);

        self::listTools($browser);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function it_keeps_every_role_on_rest_with_the_same_token(): void
    {
        $browser = self::mcpBrowser();
        self::allowOnMcp('analyst');
        $roundId = self::round('2026-11-28T19:00:00+00:00', self::walker('Piet K.'))->id()->toRfc4122();
        self::token(self::member(Role::Walker));

        $browser->request('PUT', '/api/v1/rounds/' . $roundId . '/walkers/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        self::assertResponseIsSuccessful();
    }

    private static function allowOnMcp(string ...$roles): void
    {
        self::service(InMemorySettingRepository::class)->add(Setting::of('integrations.mcp_roles', \implode(',', $roles)));
    }

    private static function listTools(KernelBrowser $browser, string $path = self::ENDPOINT): void
    {
        $browser->request(
            'POST',
            $path,
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
}
