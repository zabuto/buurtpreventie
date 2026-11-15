<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Doctrine\DoctrineTransaction;
use App\Tests\Database\TestDatabase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every test runs inside the rolled-back transaction of the test bundle, so "committed" means: the nesting level is
 * back where it started and the row is there.
 */
#[Group('database')]
final class DoctrineTransactionTest extends KernelTestCase
{
    private Connection $connection;
    private DoctrineTransaction $transaction;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->connection = $entityManager->getConnection();
        $this->transaction = new DoctrineTransaction($entityManager);
        // A temporary table commits nothing, so the rollback of each test also empties it. It lasts as long as the
        // connection of the test bundle, which every test shares.
        $this->connection->executeStatement('CREATE TEMPORARY TABLE IF NOT EXISTS transaction_probe (name VARCHAR(20) NOT NULL)');
    }

    #[Test]
    public function it_keeps_the_changes_and_returns_the_result_of_the_operation(): void
    {
        $level = $this->connection->getTransactionNestingLevel();

        $result = $this->transaction->run(fn(): string => (string) $this->connection->insert('transaction_probe', ['name' => 'kept']));

        $this->assertSame('1', $result);
        $this->assertSame($level, $this->connection->getTransactionNestingLevel());
        $this->assertSame(['kept'], $this->connection->fetchFirstColumn('SELECT name FROM transaction_probe'));
    }

    #[Test]
    public function it_undoes_the_changes_and_passes_on_the_exception_when_the_operation_fails(): void
    {
        $level = $this->connection->getTransactionNestingLevel();
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $this->transaction->run(function () use ($refusal): never {
                $this->connection->insert('transaction_probe', ['name' => 'undone']);

                throw $refusal;
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame($refusal, $caught);
        $this->assertSame($level, $this->connection->getTransactionNestingLevel());
        $this->assertSame([], $this->connection->fetchFirstColumn('SELECT name FROM transaction_probe'));
    }

    /**
     * A handler that calls the handler of another module runs that handler's transaction inside its own; a failure in
     * the inner one undoes the outer change too, so a deactivated member never keeps a usable token.
     */
    #[Test]
    public function it_undoes_the_outer_changes_when_a_nested_operation_fails(): void
    {
        $level = $this->connection->getTransactionNestingLevel();
        $refusal = new DomainException('Access denied.');

        $caught = null;
        try {
            $this->transaction->run(function () use ($refusal): void {
                $this->connection->insert('transaction_probe', ['name' => 'outer']);
                $this->transaction->run(function () use ($refusal): never {
                    $this->connection->insert('transaction_probe', ['name' => 'inner']);

                    throw $refusal;
                });
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame($refusal, $caught);
        $this->assertSame($level, $this->connection->getTransactionNestingLevel());
        $this->assertSame([], $this->connection->fetchFirstColumn('SELECT name FROM transaction_probe'));
    }
}
