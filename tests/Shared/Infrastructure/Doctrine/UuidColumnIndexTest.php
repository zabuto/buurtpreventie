<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Tests\Database\TestDatabase;
use Doctrine\DBAL\Connection;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A reference to a member of another module has no foreign key, so the database makes no index for it by itself;
 * without one, "the rounds of this member" reads the whole table. The schema comes from the migrations, so this
 * checks what ships.
 */
#[Group('database')]
final class UuidColumnIndexTest extends KernelTestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $connection = self::getContainer()->get(Connection::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;
    }

    #[Test]
    public function every_uuid_column_outside_a_primary_key_leads_an_index(): void
    {
        $withoutIndex = $this->connection->fetchFirstColumn(
            "SELECT CONCAT(c.TABLE_NAME, '.', c.COLUMN_NAME) FROM information_schema.COLUMNS c"
                . " WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_TYPE = 'binary(16)' AND c.COLUMN_KEY <> 'PRI'"
                . ' AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS s WHERE s.TABLE_SCHEMA = c.TABLE_SCHEMA'
                . ' AND s.TABLE_NAME = c.TABLE_NAME AND s.COLUMN_NAME = c.COLUMN_NAME AND s.SEQ_IN_INDEX = 1)',
        );

        $this->assertSame([], $withoutIndex);
    }

    #[Test]
    public function the_check_sees_every_uuid_column_outside_a_primary_key(): void
    {
        $columns = $this->connection->fetchFirstColumn(
            "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) FROM information_schema.COLUMNS"
                . " WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_TYPE = 'binary(16)' AND COLUMN_KEY <> 'PRI'",
        );

        $this->assertEqualsCanonicalizing(
            [
                'participation.member_id',
                'participation.on_behalf_of',
                'participation.round_id',
                'round.meeting_point_id',
                'round_comment.author_id',
                'round_comment.round_id',
                'round_report.author_id',
                'round_report.outcome_id',
                'round_report.round_id',
                'account.member_id',
                'api_token.member_id',
                'invitation.member_id',
                'login_challenge.member_id',
                'audit_entry.actor_id',
                'audit_entry.subject_id',
            ],
            $columns,
        );
    }
}
