<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Tests\Database\TestDatabase;
use Doctrine\DBAL\Connection;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * MariaDB has a native UUID type that MySQL lacks. A column declared as UUID on MariaDB only would give the two
 * databases a different schema from the same migration, so every UUID column stays BINARY(16) on both.
 */
#[Group('database')]
final class UuidColumnTest extends KernelTestCase
{
    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
    }

    #[Test]
    public function a_uuid_column_is_sixteen_bytes_on_the_connected_database(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(Connection::class, $connection);

        $declaration = new UuidType()->getSQLDeclaration([], $connection->getDatabasePlatform());

        $this->assertSame('BINARY(16)', $declaration);
    }
}
