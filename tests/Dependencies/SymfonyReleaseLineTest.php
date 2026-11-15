<?php

declare(strict_types=1);

namespace App\Tests\Dependencies;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Symfony components only work together on the same release line, and v4 follows the line in
 * extra.symfony.require (7.4 LTS). These checks stop a dependency update that would mix lines, such as one component
 * on 8.1 next to the rest on 7.4, from passing CI.
 */
#[Group('integration')]
final class SymfonyReleaseLineTest extends TestCase
{
    /** Symfony packages with a release line of their own. */
    private const array OWN_RELEASE_LINE = ['/^symfony\/flex$/', '/^symfony\/polyfill-/', '/-contracts$/'];

    #[Test]
    public function every_required_symfony_component_uses_the_release_line(): void
    {
        $composer = new ComposerFiles(__DIR__ . '/../..');
        $line = $composer->symfonyReleaseLine();
        $this->assertNotNull($line, 'composer.json lacks extra.symfony.require.');

        $requirements = \array_filter($composer->requirements(), $this->isComponent(...), \ARRAY_FILTER_USE_KEY);

        foreach ($requirements as $package => $constraint) {
            $this->assertSame($line, $constraint, \sprintf('%s requires "%s"; the Symfony release line is "%s".', $package, $constraint, $line));
        }
    }

    #[Test]
    public function every_locked_symfony_component_is_on_the_release_line(): void
    {
        $composer = new ComposerFiles(__DIR__ . '/../..');
        $line = $composer->symfonyReleaseLine();
        $this->assertNotNull($line, 'composer.json lacks extra.symfony.require.');
        $pattern = '/^v?' . \str_replace(['.', '*'], ['\.', '\d+(?:\.\d+)*'], $line) . '$/';

        $versions = \array_filter($composer->lockedVersions(), $this->isComponent(...), \ARRAY_FILTER_USE_KEY);

        $this->assertNotEmpty($versions, 'composer.lock lists no Symfony components.');
        foreach ($versions as $package => $version) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $version,
                \sprintf(
                    '%s is locked at %s, outside the Symfony release line "%s". If it has a release line of its own, add it to OWN_RELEASE_LINE.',
                    $package,
                    $version,
                    $line,
                ),
            );
        }
    }

    private function isComponent(string $package): bool
    {
        if (!\str_starts_with($package, 'symfony/')) {
            return false;
        }

        return \array_all(self::OWN_RELEASE_LINE, static fn(string $pattern): bool => 1 !== \preg_match($pattern, $package));
    }
}
