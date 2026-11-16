<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Throwable;

/**
 * Runs the operation in a database transaction and flushes the entity manager before the commit, so repositories
 * only persist and the transaction decides when it is written.
 *
 * When the operation throws, Doctrine rolls back and closes the entity manager, as its unit of work differs from the
 * database. The outermost run resets it, so the next use case in the same process (the next fixture or scheduled
 * job) gets a working one; a nested run leaves the reset to the outermost run, which must roll back its own part.
 * A failed nested run therefore always fails the outermost run too, so callbacks wait for the outermost run only.
 */
final class DoctrineTransaction implements Transaction
{
    private int $depth = 0;

    /** @var list<callable(): void> */
    private array $afterCommit = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
    }

    #[Override]
    public function run(callable $operation): mixed
    {
        $entityManager = $this->registry->getManager();
        \assert($entityManager instanceof EntityManagerInterface, 'The default manager of the ORM is an entity manager.');

        ++$this->depth;
        try {
            $result = $entityManager->wrapInTransaction(static fn(): mixed => $operation());
        } catch (Throwable $exception) {
            if (1 === $this->depth) {
                $this->afterCommit = [];
                $this->registry->resetManager();
            }

            throw $exception;
        } finally {
            --$this->depth;
        }

        if (0 === $this->depth) {
            $this->runAfterCommit();
        }

        return $result;
    }

    #[Override]
    public function afterCommit(callable $callback): void
    {
        if (0 === $this->depth) {
            $callback();

            return;
        }

        $this->afterCommit[] = $callback;
    }

    /**
     * Takes the list before running it: a callback that starts a run of its own runs that run's callbacks itself, so
     * nothing runs twice.
     */
    private function runAfterCommit(): void
    {
        $callbacks = $this->afterCommit;
        $this->afterCommit = [];
        foreach ($callbacks as $callback) {
            $callback();
        }
    }
}
