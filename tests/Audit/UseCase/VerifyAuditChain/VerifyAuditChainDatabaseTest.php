<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\VerifyAuditChain;

use App\Audit\Domain\AuditEntryRepository;
use App\Audit\UseCase\EraseActorName\EraseActorName;
use App\Audit\UseCase\EraseActorName\EraseActorNameHandler;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChain;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainHandler;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainOutput;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Shared\Domain\Transaction;
use App\Shared\Infrastructure\Security\RunAs;
use App\Tests\Database\TestDatabase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Entries are written through the services of the app and changed with SQL, as someone with access to the database
 * would. Each test starts from an empty log inside the transaction the test bundle rolls back.
 */
#[Group('database')]
final class VerifyAuditChainDatabaseTest extends KernelTestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $this->connection = self::service(EntityManagerInterface::class)->getConnection();
        $this->connection->executeStatement('DELETE FROM audit_entry');
        $this->connection->executeStatement("UPDATE audit_chain SET chain_position = 0, chain_hash = UNHEX(REPEAT('00', 32))");
    }

    #[Test]
    public function it_reports_entries_written_by_the_app_as_intact(): void
    {
        $this->written(3);

        $output = $this->verify();

        $this->assertSame([true, 3, 3], [$output->intact, $output->intactThrough, $output->lastPosition]);
    }

    #[Test]
    public function it_points_at_an_entry_whose_action_was_changed_in_the_database(): void
    {
        $this->written(3);
        $this->connection->executeStatement("UPDATE audit_entry SET action = 'round.deleted' WHERE chain_position = 2");

        $output = $this->verify();

        $this->assertSame([false, 'changed_content', 2], [$output->intact, $output->breakReason, $output->breakPosition]);
    }

    #[Test]
    public function it_points_at_an_entry_removed_from_the_database(): void
    {
        $this->written(3);
        $this->connection->executeStatement('DELETE FROM audit_entry WHERE chain_position = 2');

        $output = $this->verify();

        $this->assertSame([false, 'missing_entry', 2], [$output->intact, $output->breakReason, $output->breakPosition]);
    }

    /**
     * Erasing leaves the chain intact and the display name of the member nowhere in the log.
     */
    #[Test]
    public function it_reports_an_intact_chain_once_a_member_is_erased(): void
    {
        $anna = Uuid::v7();
        $this->record(static fn(AuditTrail $trail) => $trail->record(
            new AuditedAction('member.added', 'member', $anna, ['displayName' => ['old' => null, 'new' => 'Anna Hughes']]),
        ));
        $handler = self::service(EraseActorNameHandler::class);
        self::service(RunAs::class)->member(new Actor(Uuid::v7(), [Role::Admin]), Channel::Console, static fn() => $handler(
            new EraseActorName($anna->toRfc4122()),
        ));

        $output = $this->verify();

        $left = $this->connection->fetchOne("SELECT COUNT(*) FROM audit_entry WHERE changes LIKE '%Anna Hughes%'");
        $this->assertSame(
            [true, 2, 1, 0],
            [
                $output->intact,
                $output->intactThrough,
                $output->erased,
                \is_numeric($left) ? (int) $left : $left,
            ],
        );
    }

    #[Test]
    public function it_reports_an_intact_chain_after_pruning(): void
    {
        $this->written(3);
        $entries = self::service(AuditEntryRepository::class);
        self::service(Transaction::class)->run(static fn() => $entries->pruneThrough(2));

        $output = $this->verify();

        $this->assertSame([true, 3, 1], [$output->intact, $output->intactThrough, $output->checked]);
    }

    private function written(int $count): void
    {
        for ($entry = 0; $entry < $count; ++$entry) {
            $this->record(static fn(AuditTrail $trail) => $trail->record(
                new AuditedAction('round.changed', 'round', Uuid::v7(), ['startsAt' => ['old' => '22:00', 'new' => '23:00']]),
            ));
        }
    }

    /**
     * @param callable(AuditTrail): void $record
     */
    private function record(callable $record): void
    {
        $trail = self::service(AuditTrail::class);
        self::service(RunAs::class)
            ->system(
                Channel::Console,
                static fn() => self::service(Transaction::class)->run(static fn() => $record($trail)),
            )
        ;
    }

    private function verify(): VerifyAuditChainOutput
    {
        self::service(EntityManagerInterface::class)->clear();
        $handler = self::service(VerifyAuditChainHandler::class);

        return self::service(RunAs::class)->system(Channel::Console, static fn(): VerifyAuditChainOutput => $handler(new VerifyAuditChain()));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id, \sprintf('The container holds %s.', $id));

        return $service;
    }
}
