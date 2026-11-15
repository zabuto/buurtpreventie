<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Doctrine\UtcDateTimeImmutableType;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\MariaDB1010Platform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The default time zone of PHP is set to one far from UTC in every test, so a conversion that silently uses it
 * shows up as a shifted hour.
 */
final class UtcDateTimeImmutableTypeTest extends TestCase
{
    private string $defaultTimeZone;

    #[Override]
    protected function setUp(): void
    {
        $this->defaultTimeZone = \date_default_timezone_get();
        \date_default_timezone_set('Pacific/Auckland');
    }

    #[Override]
    protected function tearDown(): void
    {
        \date_default_timezone_set($this->defaultTimeZone);
    }

    #[Test]
    public function it_stores_a_local_time_as_its_utc_clock_time(): void
    {
        $summerEvening = new DateTimeImmutable('2026-07-01 22:00:00', new DateTimeZone('Europe/Amsterdam'));

        $stored = $this->type()->convertToDatabaseValue($summerEvening, new MariaDB1010Platform());

        $this->assertSame('2026-07-01 20:00:00', $stored);
    }

    #[Test]
    public function it_reads_a_stored_value_as_utc_whatever_the_time_zone_of_php(): void
    {
        $read = $this->type()->convertToPHPValue('2026-07-01 20:00:00', new MariaDB1010Platform());

        $this->assertInstanceOf(DateTimeImmutable::class, $read);
        $this->assertSame('2026-07-01T20:00:00.000000+00:00', $read->format('Y-m-d\TH:i:s.uP'));
        $this->assertSame('UTC', $read->getTimezone()->getName());
    }

    #[Test]
    public function it_passes_null_through_both_ways(): void
    {
        $type = $this->type();
        $platform = new MariaDB1010Platform();

        $this->assertNull($type->convertToDatabaseValue(null, $platform));
        $this->assertNull($type->convertToPHPValue(null, $platform));
    }

    #[Test]
    public function it_refuses_a_mutable_date_time(): void
    {
        $this->expectException(InvalidType::class);

        $this->type()->convertToDatabaseValue(new DateTime('2026-07-01 22:00:00'), new MariaDB1010Platform());
    }

    #[Test]
    public function it_refuses_a_stored_value_in_another_format(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->type()->convertToPHPValue('1 July 2026 20:00', new MariaDB1010Platform());
    }

    /**
     * Doctrine converts through the Type contract, so the tests do too.
     */
    private function type(): Type
    {
        return new UtcDateTimeImmutableType();
    }
}
