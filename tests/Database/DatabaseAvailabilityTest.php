<?php

declare(strict_types=1);

namespace App\Tests\Database;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Framework\TestCase;

final class DatabaseAvailabilityTest extends TestCase
{
    #[Test]
    public function it_lets_a_database_test_run_on_a_prepared_schema(): void
    {
        $availability = DatabaseAvailability::ready();

        $stoppedBecause = null;
        try {
            $availability->enforce(required: true);
        } catch (AssertionFailedError|SkippedWithMessageException $stopped) {
            $stoppedBecause = $stopped->getMessage();
        }

        $this->assertNull($stoppedBecause);
    }

    #[Test]
    public function it_skips_with_the_reason_when_the_database_is_unreachable(): void
    {
        $availability = DatabaseAvailability::unreachable('Connection refused');

        try {
            $availability->enforce(required: false);
            $this->fail('An unreachable database must skip the test.');
        } catch (SkippedWithMessageException $skipped) {
            $this->assertStringContainsString('Connection refused', $skipped->getMessage());
        }
    }

    #[Test]
    public function it_fails_on_an_unreachable_database_when_one_is_required(): void
    {
        $availability = DatabaseAvailability::unreachable('Connection refused');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        $availability->enforce(required: true);
    }

    #[Test]
    public function it_fails_when_the_migrations_break_even_when_no_database_is_required(): void
    {
        $availability = DatabaseAvailability::broken('Migration Version20261115 failed');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/Migration Version20261115 failed/');

        $availability->enforce(required: false);
    }

    #[Test]
    public function it_fails_when_no_schema_was_prepared_even_when_no_database_is_required(): void
    {
        $availability = DatabaseAvailability::notPrepared();

        $this->expectException(AssertionFailedError::class);

        $availability->enforce(required: false);
    }
}
