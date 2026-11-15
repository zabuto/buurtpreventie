<?php

declare(strict_types=1);

namespace App\Tests\Dependencies;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * A hijacked release is usually withdrawn within days, so Dependabot waits before it proposes a new version, and
 * automatic merging never pulls one in on its first day. The cooldown holds for every ecosystem without exceptions:
 * a trusted project can still have a hijacked maintainer account. Security updates are not subject to it.
 */
#[Group('integration')]
final class DependabotCooldownTest extends TestCase
{
    /** Minimum days per key; Dependabot supports the semver keys for Composer, not for GitHub Actions. */
    private const array MINIMUM_DAYS = [
        'composer' => ['semver-patch-days' => 3, 'semver-minor-days' => 7, 'semver-major-days' => 14, 'default-days' => 7],
        'github-actions' => ['default-days' => 7],
    ];

    #[Test]
    public function every_ecosystem_waits_at_least_the_agreed_days_before_proposing_an_update(): void
    {
        $updates = $this->updates();

        $this->assertSame(\array_keys(self::MINIMUM_DAYS), \array_keys($updates), 'Every Dependabot ecosystem needs a cooldown in MINIMUM_DAYS.');
        foreach (self::MINIMUM_DAYS as $ecosystem => $minimums) {
            foreach ($minimums as $key => $minimum) {
                $days = $updates[$ecosystem][$key] ?? null;
                $this->assertIsInt($days, \sprintf('%s lacks cooldown.%s.', $ecosystem, $key));
                $this->assertGreaterThanOrEqual($minimum, $days, \sprintf('%s waits %d days for %s; the minimum is %d.', $ecosystem, $days, $key, $minimum));
            }
        }
    }

    #[Test]
    public function no_dependency_is_exempt_from_the_cooldown(): void
    {
        $updates = $this->updates();

        foreach ($updates as $ecosystem => $cooldown) {
            $this->assertArrayNotHasKey('exclude', $cooldown, \sprintf('%s exempts dependencies from the cooldown.', $ecosystem));
            $this->assertArrayNotHasKey('include', $cooldown, \sprintf('%s limits the cooldown to some dependencies.', $ecosystem));
        }
    }

    /**
     * @return array<string, array<string, mixed>> ecosystem => its cooldown block, empty when it has none
     */
    private function updates(): array
    {
        $config = Yaml::parseFile(__DIR__ . '/../../.github/dependabot.yml');
        $this->assertIsArray($config);
        $this->assertIsArray($config['updates'] ?? null);

        $updates = [];
        foreach ($config['updates'] as $update) {
            $this->assertIsArray($update);
            $this->assertIsString($update['package-ecosystem'] ?? null);
            $cooldown = $update['cooldown'] ?? [];
            $this->assertIsArray($cooldown);
            $updates[$update['package-ecosystem']] = $cooldown;
        }

        return $updates;
    }
}
