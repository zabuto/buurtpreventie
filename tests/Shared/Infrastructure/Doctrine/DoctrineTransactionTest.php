<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Doctrine\DoctrineTransaction;
use App\Tests\Database\TestDatabase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use DomainException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every test runs inside the rolled-back transaction of the test bundle, so "committed" means: the nesting level is
 * at its starting value and the row is there.
 */
#[Group('database')]
final class DoctrineTransactionTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DoctrineTransaction $transaction;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $registry = self::getContainer()->get(ManagerRegistry::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(ManagerRegistry::class, $registry);
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
        $this->transaction = new DoctrineTransaction($registry);
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

    /**
     * A mail about a change must leave only once the change is stored: sent from inside the transaction, it goes out
     * for a change that a later step still undoes.
     */
    #[Test]
    public function it_runs_a_callback_once_the_outermost_run_has_committed(): void
    {
        $level = $this->connection->getTransactionNestingLevel();
        $seen = [];

        $this->transaction->run(function () use (&$seen): void {
            $this->transaction->run(function () use (&$seen): void {
                $this->connection->insert('transaction_probe', ['name' => 'stored']);
                $this->transaction->afterCommit(function () use (&$seen): void {
                    $seen[] = [$this->connection->getTransactionNestingLevel(), $this->connection->fetchFirstColumn('SELECT name FROM transaction_probe')];
                });
            });
            $seen[] = 'inner run done';
        });

        $this->assertSame(['inner run done', [$level, ['stored']]], $seen);
    }

    #[Test]
    public function it_drops_the_callbacks_of_a_run_that_fails(): void
    {
        $ran = false;
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $this->transaction->run(function () use (&$ran, $refusal): never {
                $this->transaction->afterCommit(static function () use (&$ran): void {
                    $ran = true;
                });

                throw $refusal;
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }
        $this->transaction->run(static fn(): null => null);

        $this->assertSame([$refusal, false], [$caught, $ran]);
    }

    #[Test]
    public function it_runs_a_callback_at_once_outside_a_run(): void
    {
        $ran = false;

        $this->transaction->afterCommit(static function () use (&$ran): void {
            $ran = true;
        });

        $this->assertTrue($ran);
    }

    /**
     * The audit chain takes its lock here: taken earlier, a transaction would hold it while it waits for the lock on a
     * round, and two such transactions could deadlock.
     */
    #[Test]
    public function it_runs_a_callback_before_commit_as_the_last_step_of_the_outermost_run(): void
    {
        $level = $this->connection->getTransactionNestingLevel();
        $seen = [];

        $this->transaction->run(function () use (&$seen, $level): void {
            $this->transaction->run(function () use (&$seen, $level): void {
                $this->transaction->beforeCommit(function () use (&$seen, $level): void {
                    $seen[] = ['before commit', $this->connection->getTransactionNestingLevel() - $level];
                });
            });
            $seen[] = 'outer operation done';
        });

        $this->assertSame(['outer operation done', ['before commit', 1]], $seen);
    }

    #[Test]
    public function it_commits_what_a_callback_before_commit_writes_together_with_the_operation(): void
    {
        $this->transaction->run(function (): void {
            $this->connection->insert('transaction_probe', ['name' => 'change']);
            $this->transaction->beforeCommit(function (): void {
                $this->connection->insert('transaction_probe', ['name' => 'entry']);
            });
        });

        $this->assertSame(['change', 'entry'], $this->connection->fetchFirstColumn('SELECT name FROM transaction_probe'));
    }

    #[Test]
    public function it_runs_a_callback_before_commit_that_another_one_adds(): void
    {
        $seen = [];

        $this->transaction->run(function () use (&$seen): void {
            $this->transaction->beforeCommit(function () use (&$seen): void {
                $seen[] = 'first';
                $this->transaction->beforeCommit(static function () use (&$seen): void {
                    $seen[] = 'added by the first';
                });
            });
        });

        $this->assertSame(['first', 'added by the first'], $seen);
    }

    #[Test]
    public function it_undoes_the_run_when_a_callback_before_commit_fails(): void
    {
        $refusal = new DomainException('No audit secret.');
        $afterCommit = false;

        $caught = null;
        try {
            $this->transaction->run(function () use ($refusal, &$afterCommit): void {
                $this->connection->insert('transaction_probe', ['name' => 'undone']);
                $this->transaction->afterCommit(static function () use (&$afterCommit): void {
                    $afterCommit = true;
                });
                $this->transaction->beforeCommit(static fn(): never => throw $refusal);
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame([$refusal, false, []], [$caught, $afterCommit, $this->connection->fetchFirstColumn('SELECT name FROM transaction_probe')]);
    }

    #[Test]
    public function it_drops_the_callbacks_before_commit_of_a_run_that_fails(): void
    {
        $ran = false;
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $this->transaction->run(function () use (&$ran, $refusal): never {
                $this->transaction->beforeCommit(static function () use (&$ran): void {
                    $ran = true;
                });

                throw $refusal;
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }
        $this->transaction->run(static fn(): null => null);

        $this->assertSame([$refusal, false], [$caught, $ran]);
    }

    /**
     * Without a run there is no commit to precede, and a lock taken outside a transaction ends at once.
     */
    #[Test]
    public function it_refuses_a_callback_before_commit_outside_a_run(): void
    {
        $this->expectException(LogicException::class);

        $this->transaction->beforeCommit(static function (): void {});
    }

    /**
     * Doctrine closes the entity manager when a transaction fails; without a reset, the next use case in the same
     * process, such as the next fixture or scheduled job, fails on a closed entity manager.
     */
    #[Test]
    public function it_leaves_a_working_entity_manager_after_a_failed_operation(): void
    {
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $this->transaction->run(static fn(): never => throw $refusal);
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame([$refusal, true], [$caught, $this->entityManager->isOpen()]);
    }
}
