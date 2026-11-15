<?php

declare(strict_types=1);

namespace App\Tests\Database;

use PHPUnit\Framework\Assert;

/**
 * Whether the database tests of this run have a schema, and what a database test does when they do not.
 *
 * An unreachable database is a missing precondition: the test skips, unless the run requires a database (CI), where
 * a skip would hide that no database test ran. A broken migration or an unprepared schema is a defect and always
 * fails.
 */
final readonly class DatabaseAvailability
{
    private const string START_DATABASE =
        'Database tests need a running database: start it with "docker compose up -d --wait" and run'
            . ' PHPUnit through the Symfony CLI ("symfony php vendor/bin/phpunit"), which sets DATABASE_URL.';

    private function __construct(
        private ?string $skipReason,
        private ?string $failure,
    ) {
    }

    public static function ready(): self
    {
        return new self(null, null);
    }

    public static function unreachable(string $problem): self
    {
        return new self(self::START_DATABASE . ' ' . $problem, null);
    }

    public static function broken(string $problem): self
    {
        return new self(null, 'Preparing the database schema failed: ' . $problem);
    }

    public static function notPrepared(): self
    {
        return new self(
            null,
            'No database schema was prepared: give the test class #[Group(\'database\')], and keep DatabaseSchemaExtension'
                . ' in phpunit.dist.xml.',
        );
    }

    public function enforce(bool $required): void
    {
        if (null !== $this->failure) {
            Assert::fail($this->failure);
        }

        if (null === $this->skipReason) {
            return;
        }

        if ($required) {
            Assert::fail($this->skipReason);
        }

        Assert::markTestSkipped($this->skipReason);
    }
}
