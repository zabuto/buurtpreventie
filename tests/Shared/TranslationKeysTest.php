<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * A text in one language and not the other shows its key to a reader of the other, or falls back unnoticed.
 */
#[Group('integration')]
final class TranslationKeysTest extends TestCase
{
    private const string TRANSLATIONS = __DIR__ . '/../../translations';

    /**
     * @return iterable<string, array{string}>
     */
    public static function domains(): iterable
    {
        $files = \glob(self::TRANSLATIONS . '/*.nl.yaml');
        foreach (false === $files ? [] : $files as $file) {
            $domain = \basename($file, '.nl.yaml');

            yield $domain => [$domain];
        }
    }

    #[Test]
    #[DataProvider('domains')]
    public function every_text_has_a_dutch_and_an_english_version(string $domain): void
    {
        $keys = static function (string $locale) use ($domain): array {
            $texts = Yaml::parseFile(self::TRANSLATIONS . '/' . $domain . '.' . $locale . '.yaml');
            $keys = \is_array($texts) ? \array_map(strval(...), \array_keys($texts)) : [];
            \sort($keys);

            return $keys;
        };

        $this->assertSame($keys('nl'), $keys('en'));
    }
}
