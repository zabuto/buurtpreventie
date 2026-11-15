<?php

declare(strict_types=1);

namespace App\Tests\Dependencies;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Composer withholds a release younger than the cooldown during every update and require, also on a developer
 * machine, where the Dependabot cooldown does not reach. Many hijacked releases are withdrawn within hours. The period
 * stays short, because a security fix that Dependabot proposes at once waits for it too.
 */
#[Group('integration')]
final class ComposerCooldownTest extends TestCase
{
    private const int MINIMUM_SECONDS = 24 * 3600;
    private const array SECONDS_PER_UNIT = ['second' => 1, 'minute' => 60, 'hour' => 3600, 'day' => 86_400, 'week' => 604_800];

    #[Test]
    public function composer_withholds_every_release_for_at_least_a_day(): void
    {
        $cooldown = $this->cooldown();

        $this->assertGreaterThanOrEqual(self::MINIMUM_SECONDS, $this->seconds($cooldown['period'] ?? null));
        $this->assertNotFalse($cooldown['block'] ?? true, 'config.policy.cooldown.block must not switch the cooldown off.');
        $this->assertArrayNotHasKey('ignore', $cooldown, 'No package is exempt from the Composer cooldown.');
    }

    /**
     * @return array<mixed>
     */
    private function cooldown(): array
    {
        $composer = \json_decode((string) \file_get_contents(__DIR__ . '/../../composer.json'), true);
        $this->assertIsArray($composer);
        $config = $composer['config'] ?? null;
        $this->assertIsArray($config, 'composer.json lacks config.');
        $policy = $config['policy'] ?? null;
        $this->assertIsArray($policy, 'composer.json lacks config.policy.');
        $cooldown = $policy['cooldown'] ?? null;
        $this->assertIsArray($cooldown, 'composer.json lacks config.policy.cooldown.');

        return $cooldown;
    }

    /**
     * Reads the forms Composer accepts: an integer number of seconds, or "<number> <unit>".
     */
    private function seconds(mixed $period): int
    {
        if (\is_int($period)) {
            return $period;
        }

        $this->assertIsString($period, 'config.policy.cooldown.period must be a duration.');
        $this->assertSame(1, \preg_match('/^(\d+)\s*(second|minute|hour|day|week)s?$/', $period, $match), \sprintf('"%s" is no Composer duration.', $period));

        return (int) $match[1] * self::SECONDS_PER_UNIT[$match[2]];
    }
}
