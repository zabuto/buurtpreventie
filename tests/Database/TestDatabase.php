<?php

declare(strict_types=1);

namespace App\Tests\Database;

/**
 * The outcome of the one schema preparation per run. A PHPUnit extension and the tests share no object, so it lives
 * in a static property that only the extension writes.
 */
final class TestDatabase
{
    private static ?DatabaseAvailability $availability = null;

    public static function prepared(DatabaseAvailability $availability): void
    {
        self::$availability = $availability;
    }

    public static function reachedTheServer(): bool
    {
        return self::$availability?->reachedTheServer() ?? false;
    }

    /**
     * Call first in the set-up of every database test: it skips or fails the test unless the schema is ready.
     * REQUIRE_DATABASE=1 turns a skip into a failure.
     */
    public static function require(): void
    {
        (self::$availability ?? DatabaseAvailability::notPrepared())->enforce(required: '1' === \getenv('REQUIRE_DATABASE'));
    }
}
