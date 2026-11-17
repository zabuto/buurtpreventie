<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Domain\TokenScope;
use App\Shared\Domain\Role;
use App\Tests\Support\McpTestCase;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every tool against scope and role. The list of tools of the plan is checked against the server itself, so a new
 * tool fails here until it joins these checks.
 */
#[Group('functional')]
final class McpMatrixTest extends McpTestCase
{
    /** Tools every member may call. */
    public const array MEMBER_TOOLS = ['list_rounds', 'get_round', 'my_rounds', 'staffing_gaps', 'list_meeting_points', 'list_outcomes'];

    /** Tools for analysts and coordinators. */
    public const array ANALYSIS_TOOLS = ['report_summary', 'round_statistics'];

    /** Tools that change something, for walkers (of the round) and coordinators. */
    public const array WRITE_TOOLS = ['sign_up_for_round', 'withdraw_from_round', 'create_round', 'report_round_result', 'add_round_comment'];

    #[Test]
    public function it_serves_exactly_the_tools_of_the_plan(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $names = \array_map(static fn(Tool $tool): string => $tool->name, self::connect($browser)->listTools()->tools);

        \sort($names);
        $expected = [...self::MEMBER_TOOLS, ...self::ANALYSIS_TOOLS, ...self::WRITE_TOOLS];
        \sort($expected);
        self::assertSame($expected, $names);
    }

    /**
     * A type list such as ["null", "string"] is valid JSON Schema, but a client that maps tool schemas onto a dialect
     * with one type per property, such as Gemini, may reject the tool. An optional argument is left out instead.
     */
    #[Test]
    public function it_gives_every_argument_of_every_tool_a_single_type(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $listed = [];
        foreach (self::connect($browser)->listTools()->tools as $tool) {
            $properties = $tool->inputSchema['properties'] ?? [];
            foreach (\is_array($properties) ? $properties : [] as $name => $property) {
                $listed[$tool->name . '.' . $name] = \is_array($property) ? $property['type'] ?? null : null;
            }
        }

        self::assertNotSame([], $listed);
        self::assertSame([], \array_keys(\array_filter($listed, static fn(mixed $type): bool => !\is_string($type))));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function tools(): iterable
    {
        foreach ([...self::MEMBER_TOOLS, ...self::ANALYSIS_TOOLS] as $tool) {
            yield $tool => [$tool, false];
        }

        foreach (self::WRITE_TOOLS as $tool) {
            yield $tool => [$tool, true];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function toolNames(): iterable
    {
        foreach (self::tools() as $tool => [$name]) {
            yield $tool => [$name];
        }
    }

    #[DataProvider('tools')]
    #[Test]
    public function it_asks_the_write_scope_for_exactly_the_tools_that_change_something(string $tool, bool $writes): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Walker, Role::Coordinator, Role::Analyst), TokenScope::Read);
        $arguments = self::toolArguments();

        $result = self::connect($browser)->callTool($tool, $arguments);

        self::assertSame($writes, \str_contains(self::text($result), 'insufficient_scope'), self::text($result));
    }

    /**
     * An administrator manages members and settings, not the content of rounds: of the tools only the ones for every
     * member are open to them.
     */
    #[DataProvider('toolNames')]
    #[Test]
    public function it_refuses_every_tool_but_those_for_every_member_to_an_administrator(string $tool): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Admin));
        $arguments = self::toolArguments();

        $result = self::connect($browser)->callTool($tool, $arguments);

        self::assertSame(!\in_array($tool, self::MEMBER_TOOLS, strict: true), \str_contains(self::text($result), 'access_denied'), self::text($result));
    }
}
