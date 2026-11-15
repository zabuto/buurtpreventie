<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Stores through the type name as registered in doctrine.yaml, so a missing registration fails here as well. PHP
 * runs in a time zone far from UTC, so a conversion that falls back to it shows up as a shifted hour.
 */
#[Group('database')]
final class UtcDateTimeImmutableTypeRoundTripTest extends KernelTestCase
{
    private string $defaultTimeZone;

    #[Override]
    protected function setUp(): void
    {
        $this->defaultTimeZone = \date_default_timezone_get();
        \date_default_timezone_set('Pacific/Auckland');
        TestDatabase::require();
    }

    #[Override]
    protected function tearDown(): void
    {
        \date_default_timezone_set($this->defaultTimeZone);
        parent::tearDown();
    }

    #[Test]
    public function it_brings_the_second_one_thirty_of_the_autumn_clock_change_back_unchanged(): void
    {
        $connection = $this->connection();
        // A temporary table commits nothing, so it leaves the rollback of each test intact.
        $connection->executeStatement('CREATE TEMPORARY TABLE utc_round_trip (starts_at DATETIME NOT NULL)');
        $winterTime = new DateTimeImmutable('2026-10-25T02:30:00+01:00');

        try {
            $connection->insert('utc_round_trip', ['starts_at' => $winterTime], ['starts_at' => 'utc_datetime_immutable']);
            $read = $connection->convertToPHPValue($connection->fetchOne('SELECT starts_at FROM utc_round_trip'), 'utc_datetime_immutable');
        } finally {
            $connection->executeStatement('DROP TEMPORARY TABLE utc_round_trip');
        }

        $this->assertInstanceOf(DateTimeImmutable::class, $read);
        $this->assertSame('2026-10-25T01:30:00+00:00', $read->format(\DATE_ATOM));
        $this->assertEquals(new DateTimeZone('UTC'), $read->getTimezone());
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
