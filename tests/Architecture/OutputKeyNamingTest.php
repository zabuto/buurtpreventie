<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The keys of the array shapes an output describes are JSON keys as well, and Nelmio leaves them out of the OpenAPI
 * description, so OpenApiNamingTest cannot see them.
 */
final class OutputKeyNamingTest extends TestCase
{
    private const string SOURCE = __DIR__ . '/../../src';

    #[Test]
    public function every_array_shape_key_of_an_output_is_camel_case(): void
    {
        $offending = [];
        $files = \glob(self::SOURCE . '/*/UseCase/*/*Output.php');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            foreach (self::shapeKeys($file) as $key) {
                if (1 === \preg_match('/^[a-z][a-zA-Z0-9]*$/', $key)) {
                    continue;
                }

                $offending[] = \basename($file) . ' ' . $key;
            }
        }

        $this->assertSame([], $offending);
    }

    /**
     * Without this, a pattern that finds no key at all would let every output pass.
     */
    #[Test]
    public function it_reads_the_keys_of_a_multi_line_shape(): void
    {
        $keys = self::shapeKeys(self::SOURCE . '/Audit/UseCase/ListAuditEntries/ListAuditEntriesOutput.php');

        $this->assertContains('occurredAt', $keys);
    }

    /**
     * @return list<string> every key of an array shape in the docblocks of the file
     */
    private static function shapeKeys(string $file): array
    {
        \preg_match_all('/[{,]\s*(?:\*\s*)?([A-Za-z_][A-Za-z0-9_]*)\??:\s/', (string) \file_get_contents($file), $keys);

        $found = [];
        foreach ($keys[1] as $key) {
            $found[] = $key;
        }

        return $found;
    }
}
