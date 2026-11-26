<?php

declare(strict_types=1);

namespace App\Tests\Dependencies;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Symfony components only work together on one release line, the one in extra.symfony.require. These checks keep a
 * dependency update that mixes lines, such as one component on 8.1 next to the rest on 7.4, from passing CI.
 */
#[Group('integration')]
final class SymfonyReleaseLineTest extends TestCase
{
    /**
     * Symfony packages with a release line of their own. The MCP bundle is split from symfony/ai, which numbers its
     * releases apart from the components.
     */
    private const array OWN_RELEASE_LINE = ['/^symfony\/flex$/', '/^symfony\/polyfill-/', '/-contracts$/', '/^symfony\/mcp-bundle$/'];

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

    /**
     * Symfony Flex holds an indirect component on the release line, but Dependabot runs Composer without Flex: there
     * only a conflict in composer.json keeps a bundle on 7.4 from pulling in a component on 8.
     */
    #[Test]
    public function every_indirect_symfony_component_conflicts_with_other_release_lines(): void
    {
        $composer = new ComposerFiles(__DIR__ . '/../..');
        $line = $composer->symfonyReleaseLine();
        $this->assertNotNull($line, 'composer.json lacks extra.symfony.require.');
        $outsideLine = self::outside($line);
        $required = $composer->requirements();
        $conflicts = $composer->conflicts();

        $indirect = \array_filter(
            $composer->lockedVersions(),
            fn(string $package): bool => $this->isComponent($package) && !\array_key_exists($package, $required),
            \ARRAY_FILTER_USE_KEY,
        );

        $this->assertNotEmpty($indirect, 'composer.lock lists no indirect Symfony components.');
        foreach (\array_keys($indirect) as $package) {
            $this->assertSame(
                $outsideLine,
                $conflicts[$package] ?? null,
                \sprintf('Add "%s": "%s" to conflict in composer.json, so an update without Symfony Flex keeps it on "%s".', $package, $outsideLine, $line),
            );
        }
    }

    /**
     * The Composer constraint for every version outside a release line such as 7.4.*.
     */
    private static function outside(string $line): string
    {
        [$major, $minor] = \array_map(\intval(...), \explode('.', $line));

        return \sprintf('<%d.%d || >=%d.%d', $major, $minor, $major, $minor + 1);
    }

    private function isComponent(string $package): bool
    {
        if (!\str_starts_with($package, 'symfony/')) {
            return false;
        }

        return \array_all(self::OWN_RELEASE_LINE, static fn(string $pattern): bool => 1 !== \preg_match($pattern, $package));
    }
}
