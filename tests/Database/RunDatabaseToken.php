<?php

declare(strict_types=1);

namespace App\Tests\Database;

/**
 * Gives every PHPUnit run a test database of its own, so two runs on one machine never drop each other's tables:
 * config/packages/doctrine.yaml appends TEST_TOKEN to the name of the test database.
 *
 * A token set from outside wins (ParaTest sets one per process), and its database stays after the run, so it can be
 * inspected. The token goes into $_SERVER and $_ENV for the kernel and into the process environment for bin/console
 * and every other process a test starts.
 */
final readonly class RunDatabaseToken
{
    private const string NAME = 'TEST_TOKEN';

    /**
     * @return bool true when the run took a token of its own, whose database it drops when it ends
     */
    public static function claim(): bool
    {
        if ('' !== self::fromOutside()) {
            return false;
        }

        $token = '_run' . \bin2hex(\random_bytes(4));
        $_SERVER[self::NAME] = $token;
        $_ENV[self::NAME] = $token;
        \putenv(self::NAME . '=' . $token);

        return true;
    }

    private static function fromOutside(): string
    {
        foreach ([$_SERVER[self::NAME] ?? null, $_ENV[self::NAME] ?? null, \getenv(self::NAME)] as $value) {
            if (\is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return '';
    }
}
