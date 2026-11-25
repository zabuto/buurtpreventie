<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Mcp;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * The plugin in plugins/buurtpreventie ships to every member who installs it, and only a new version reaches them: a
 * mistake here stays on their machines until the next release of the plugin.
 */
final class AgentPluginTest extends TestCase
{
    private const string PLUGIN = __DIR__ . '/../../../../plugins/buurtpreventie';

    /**
     * A tool the skill does not name is one the model chooses without guidance; a name the server does not serve sends
     * the model after a tool that fails.
     */
    #[Test]
    public function it_names_exactly_the_tools_of_the_server_in_the_skill(): void
    {
        $named = self::namedInSkill();
        $tools = self::attributeNames(McpTool::class);

        $missing = \array_values(\array_diff($tools, $named));
        $unknown = \array_values(\array_diff($named, [...$tools, ...self::attributeNames(McpPrompt::class)]));

        $this->assertSame(['missing' => [], 'unknown' => []], ['missing' => $missing, 'unknown' => $unknown]);
    }

    /**
     * Agent Skills requires the name to match the folder; Cursor and Copilot skip a skill whose name does not.
     */
    #[Test]
    public function it_names_the_skill_after_its_folder(): void
    {
        $this->assertSame(1, \preg_match('/^---\n(?:.*\n)*?name: (\S+)\n(?:.*\n)*?---\n/', self::skill(), $match));
        $this->assertSame('buurtpreventie', $match[1]);
    }

    /**
     * A token written into the plugin would reach every member who installs it, and anyone who reads the repository.
     */
    #[Test]
    public function it_takes_the_token_only_from_the_configuration_of_the_member(): void
    {
        $config = self::json(self::PLUGIN . '/.mcp.json');

        $this->assertSame(
            [
                'mcpServers' => [
                    'buurtpreventie' => [
                        'type' => 'http',
                        'url' => '${user_config.server_url}/mcp',
                        'headers' => ['Authorization' => 'Bearer ${user_config.token}'],
                    ],
                ],
            ],
            $config,
        );
    }

    /**
     * Claude Code reads the marketplace from the default branch, develop. Without the pin, a member would install a
     * plugin that names tools before the release of the app that serves them.
     */
    #[Test]
    public function it_installs_the_plugin_from_the_released_branch(): void
    {
        $marketplace = self::json(__DIR__ . '/../../../../.claude-plugin/marketplace.json');

        $this->assertSame(
            [[
                'name' => 'buurtpreventie',
                'source' => ['source' => 'git-subdir', 'url' => 'zabuto/buurtpreventie', 'path' => 'plugins/buurtpreventie', 'ref' => 'main'],
                'description' => 'Plan neighbourhood watch rounds from an AI client through the MCP server of your own Buurtpreventie app',
            ]],
            $marketplace['plugins'] ?? null,
        );
    }

    /**
     * The guide promises that the plugin runs no code on the member's computer: a hook, script, agent or second server
     * would break that promise without any other test noticing.
     */
    #[Test]
    public function it_ships_only_configuration_a_skill_and_a_readme(): void
    {
        $files = [];
        $root = (string) \realpath(self::PLUGIN);
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $files[] = \substr($file->getPathname(), \strlen($root) + 1);
        }
        \sort($files);

        $this->assertSame(['.claude-plugin/plugin.json', '.mcp.json', 'README.md', 'skills/buurtpreventie/SKILL.md'], $files);
    }

    /**
     * Claude Code keeps a member on the installed copy until the version changes; a version it cannot compare leaves
     * them there for good.
     */
    #[Test]
    public function it_gives_the_plugin_a_semantic_version(): void
    {
        $version = self::json(self::PLUGIN . '/.claude-plugin/plugin.json')['version'] ?? null;

        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/D', $version);
    }

    /**
     * Every snake_case name in backticks in the skill: tools and prompts are the only snake_case names it uses.
     *
     * @return list<string>
     */
    private static function namedInSkill(): array
    {
        \preg_match_all('/`([a-z]+(?:_[a-z]+)+)`/', self::skill(), $matches);

        return \array_values(\array_unique($matches[1]));
    }

    /**
     * @param class-string<McpTool|McpPrompt> $attribute
     *
     * @return list<string>
     */
    private static function attributeNames(string $attribute): array
    {
        $source = \dirname(__DIR__, 4) . '/src';
        $names = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $name = 'App\\' . \str_replace('/', '\\', \substr($file->getPathname(), \strlen($source) + 1, -4));
            if ('php' !== $file->getExtension() || !\class_exists($name)) {
                continue;
            }

            foreach (new ReflectionClass($name)->getAttributes($attribute) as $found) {
                $names[] = (string) $found->newInstance()->name;
            }
        }

        return $names;
    }

    private static function skill(): string
    {
        $skill = \file_get_contents(self::PLUGIN . '/skills/buurtpreventie/SKILL.md');
        self::assertIsString($skill);

        return $skill;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function json(string $path): array
    {
        $contents = \file_get_contents($path);
        self::assertIsString($contents);
        $decoded = \json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
