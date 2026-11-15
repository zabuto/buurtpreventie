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
 */
final class DoctrineTransaction implements Transaction
{
    private int $depth = 0;

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
            return $entityManager->wrapInTransaction(static fn(): mixed => $operation());
        } catch (Throwable $exception) {
            if (1 === $this->depth) {
                $this->registry->resetManager();
            }

            throw $exception;
        } finally {
            --$this->depth;
        }
    }
}
