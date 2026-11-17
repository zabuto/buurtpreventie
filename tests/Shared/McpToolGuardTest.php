<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Holds every MCP tool to the definition of done: it is bound to the server, says whether it only reads (which
 * decides its scope), and has a test of its own next to the handler test, with a parity test against REST.
 */
#[Group('integration')]
final class McpToolGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function tools(): iterable
    {
        $source = \dirname(__DIR__, 2) . '/src/';
        $files = \glob($source . '*/UseCase/*/*Tool.php');
        foreach (false === $files ? [] : $files as $file) {
            $relative = \substr($file, \strlen($source), -4);
            $class = 'App\\' . \str_replace('/', '\\', $relative);
            \assert(\class_exists($class), 'Every PHP file under src/ declares the class its path names.');

            yield $class => [$class];
        }
    }

    /**
     * @param class-string $tool
     */
    #[DataProvider('tools')]
    #[Test]
    public function every_tool_says_whether_it_only_reads(string $tool): void
    {
        $attributes = new ReflectionClass($tool)->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, $tool . ' needs #[McpTool] to reach the server.');
        $this->assertIsBool($attributes[0]->newInstance()->annotations?->readOnlyHint, $tool . ' needs a readOnlyHint: it decides the scope a token needs.');
    }

    /**
     * @param class-string $tool
     */
    #[DataProvider('tools')]
    #[Test]
    public function every_tool_has_a_test_that_compares_it_with_rest(string $tool): void
    {
        $testFile = \dirname(__DIR__) . '/' . \str_replace('\\', '/', \substr($tool, \strlen('App\\'))) . 'Test.php';

        $this->assertFileExists($testFile, $tool . ' needs a test of its own.');
        $this->assertStringContainsString('self::rest', (string) \file_get_contents($testFile), $tool . ' needs a parity test against REST.');
    }
}
