<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure\Persistence;

use App\Tests\Database\SchemaPreparation;
use App\Tests\Database\TestDatabase;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Entries stored before the chain existed get a position by time and id, and the head and anchor take the last of
 * them, so the first new entry links to the start hash and the check starts behind them.
 *
 * The migrations run in processes of their own and commit their DDL, so the test bundle cannot roll back; the test
 * removes its entries and leaves the schema at the latest version.
 */
#[Group('database')]
#[SkipDatabaseRollback]
final class AuditChainMigrationTest extends KernelTestCase
{
    private const string BEFORE_THE_CHAIN = 'DoctrineMigrations\Version20261116120000';

    private Connection $connection;

    /** Without a database setUp skips before it connects, and tearDown has nothing to undo. */
    private bool $connected = false;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->connection = $entityManager->getConnection();
        $this->connected = true;
        $this->connection->executeStatement('DELETE FROM audit_entry');
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->connected) {
            $this->assertNull(SchemaPreparation::migrateTo('latest'));
            $this->connection->executeStatement('DELETE FROM audit_entry');
            $this->connection->executeStatement("UPDATE audit_chain SET chain_position = 0, chain_hash = UNHEX(REPEAT('00', 32))");
        }

        parent::tearDown();
    }

    #[Test]
    public function the_migration_numbers_existing_entries_by_time_and_id_and_puts_head_and_anchor_behind_them(): void
    {
        $this->assertNull(SchemaPreparation::migrateTo(self::BEFORE_THE_CHAIN));
        $laterLowId = Uuid::fromString('0196b0a4-0000-7000-8000-000000000001');
        $laterHighId = Uuid::fromString('0196b0a4-0000-7000-8000-000000000002');
        $earlier = Uuid::fromString('0196b0a4-0000-7000-8000-000000000003');
        $this->storedBeforeTheChain($laterHighId, '2026-11-15 12:00:00');
        $this->storedBeforeTheChain($earlier, '2026-11-14 12:00:00');
        $this->storedBeforeTheChain($laterLowId, '2026-11-15 12:00:00');

        $this->assertNull(SchemaPreparation::migrateTo('latest'));

        $this->assertSame(
            [
                [$earlier->toBinary(), 1, null, null],
                [$laterLowId->toBinary(), 2, null, null],
                [$laterHighId->toBinary(), 3, null, null],
            ],
            $this->connection->fetchAllNumeric('SELECT id, chain_position, changes_hash, chain_hash FROM audit_entry ORDER BY chain_position'),
        );
        $this->assertSame(
            [['anchor', 3, \str_repeat("\0", 32)], ['head', 3, \str_repeat("\0", 32)]],
            $this->connection->fetchAllNumeric('SELECT name, chain_position, chain_hash FROM audit_chain ORDER BY name'),
        );
    }

    #[Test]
    public function the_migration_starts_an_empty_log_at_position_zero(): void
    {
        $this->assertNull(SchemaPreparation::migrateTo(self::BEFORE_THE_CHAIN));

        $this->assertNull(SchemaPreparation::migrateTo('latest'));

        $this->assertSame(
            [['anchor', 0], ['head', 0]],
            $this->connection->fetchAllNumeric('SELECT name, chain_position FROM audit_chain ORDER BY name'),
        );
    }

    private function storedBeforeTheChain(Uuid $id, string $occurredAt): void
    {
        $this->connection->insert('audit_entry', [
            'id' => $id->toBinary(),
            'changes' => '{"displayName":{"old":null,"new":"Anna H."}}',
            'occurred_at' => $occurredAt,
            'actor_id' => null,
            'actor_name' => null,
            'action' => 'member.added',
            'subject_type' => 'member',
            'subject_id' => Uuid::v7()->toBinary(),
            'channel' => 'console',
        ]);
    }
}
