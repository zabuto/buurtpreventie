<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architecture errors send the reader to a section of docs/architecture.md; a renamed heading would leave them at
 * the top of the page without a hint.
 */
#[Group('integration')]
final class DocumentationLinksTest extends TestCase
{
    private const string PROJECT_ROOT = __DIR__ . '/../..';

    /**
     * @return array<string, array{string}>
     */
    public static function linkedSections(): array
    {
        $files = [
            ...self::files(self::PROJECT_ROOT . '/src', 'php'),
            ...self::files(__DIR__, 'php', exclude: __DIR__ . '/Fixtures'),
            ...self::files(self::PROJECT_ROOT . '/docs', 'md'),
        ];
        $anchors = [];
        foreach ($files as $file) {
            \preg_match_all("/architecture\\.md#([a-z0-9-]+)|DOCS\\s*\\.\\s*'([a-z0-9-]+)'/", (string) \file_get_contents($file), $matches);
            foreach (\array_filter([...$matches[1], ...$matches[2]]) as $anchor) {
                $anchors[$anchor] = [$anchor];
            }
        }
        \ksort($anchors);

        return $anchors;
    }

    #[DataProvider('linkedSections')]
    #[Test]
    public function linked_section_exists_in_the_architecture_document(string $anchor): void
    {
        self::assertContains($anchor, self::headingAnchors(), \sprintf('docs/architecture.md has no heading for #%s.', $anchor));
    }

    /**
     * GitHub turns a heading into an anchor by lower-casing it, dropping punctuation and joining words with hyphens.
     *
     * @return list<string>
     */
    private static function headingAnchors(): array
    {
        \preg_match_all('/^#{1,6} (.+)$/m', (string) \file_get_contents(self::PROJECT_ROOT . '/docs/architecture.md'), $matches);

        $anchors = [];
        foreach ($matches[1] as $heading) {
            $anchors[] = \str_replace(' ', '-', (string) \preg_replace('/[^a-z0-9 -]/', '', \strtolower($heading)));
        }

        return $anchors;
    }

    /**
     * @return list<string>
     */
    private static function files(string $path, string $extension, ?string $exclude = null): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            \assert($file instanceof SplFileInfo, 'RecursiveDirectoryIterator yields SplFileInfo by default.');
            if ($extension === $file->getExtension() && (null === $exclude || !\str_starts_with($file->getPathname(), $exclude))) {
                $files[] = $file->getPathname();
            }
        }
        \sort($files);

        return $files;
    }
}
