<?php

declare(strict_types=1);

namespace App\Tests\Dependencies;

use PHPUnit\Framework\TestCase;

/**
 * Symfony components only work together on the same release line, and v4 follows the line in
 * extra.symfony.require (7.4 LTS). These checks stop a dependency update that would mix lines, such as one component
 * on 8.1 next to the rest on 7.4, from passing CI.
 */
final class SymfonyReleaseLineTest extends TestCase
{
    private const string PROJECT_ROOT = __DIR__.'/../..';

    /** Symfony packages with a release line of their own. */
    private const array OWN_RELEASE_LINE = ['/^symfony\/flex$/', '/^symfony\/polyfill-/', '/-contracts$/'];

    public function testEveryRequiredSymfonyComponentUsesTheReleaseLine(): void
    {
        $line = self::releaseLine();
        $composer = self::json('composer.json');

        foreach (['require', 'require-dev'] as $section) {
            $requirements = $composer[$section] ?? [];
            self::assertIsArray($requirements);
            foreach ($requirements as $package => $constraint) {
                if (self::isComponent((string) $package)) {
                    self::assertSame($line, $constraint, \sprintf('%s requires "%s"; the Symfony release line is "%s".', $package, (string) (\is_scalar($constraint) ? $constraint : ''), $line));
                }
            }
        }
    }

    public function testEveryLockedSymfonyComponentIsOnTheReleaseLine(): void
    {
        $line = self::releaseLine();
        $pattern = '/^v?'.str_replace(['.', '*'], ['\.', '\d+(?:\.\d+)*'], $line).'$/';
        $lock = self::json('composer.lock');
        $packages = [...(\is_array($lock['packages'] ?? null) ? $lock['packages'] : []), ...(\is_array($lock['packages-dev'] ?? null) ? $lock['packages-dev'] : [])];

        $checked = 0;
        foreach ($packages as $package) {
            self::assertIsArray($package);
            $name = \is_string($package['name'] ?? null) ? $package['name'] : '';
            $version = \is_string($package['version'] ?? null) ? $package['version'] : '';
            if (!self::isComponent($name)) {
                continue;
            }
            ++$checked;
            self::assertMatchesRegularExpression($pattern, $version, \sprintf(
                '%s is locked at %s, outside the Symfony release line "%s". If it has a release line of its own, add it to OWN_RELEASE_LINE.',
                $name,
                $version,
                $line,
            ));
        }

        self::assertGreaterThan(0, $checked, 'composer.lock lists no Symfony components.');
    }

    private static function releaseLine(): string
    {
        $extra = self::json('composer.json')['extra'] ?? null;
        $symfony = \is_array($extra) ? ($extra['symfony'] ?? null) : null;
        $line = \is_array($symfony) ? ($symfony['require'] ?? null) : null;
        self::assertIsString($line, 'composer.json lacks extra.symfony.require.');

        return $line;
    }

    private static function isComponent(string $package): bool
    {
        if (!str_starts_with($package, 'symfony/')) {
            return false;
        }

        return array_all(self::OWN_RELEASE_LINE, static fn (string $pattern): bool => 1 !== preg_match($pattern, $package));
    }

    /**
     * @return array<mixed>
     */
    private static function json(string $file): array
    {
        $data = json_decode((string) file_get_contents(self::PROJECT_ROOT.'/'.$file), true);
        self::assertIsArray($data, $file.' is not valid JSON.');

        return $data;
    }
}
