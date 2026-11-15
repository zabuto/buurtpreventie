<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Override;

/**
 * Runs the operation in a database transaction and flushes the entity manager before the commit, so repositories
 * only persist and the transaction decides when it is written.
 *
 * When the operation throws, Doctrine rolls back and closes the entity manager: its unit of work no longer matches
 * the database. A long-running process has to reset the entity manager before the next unit of work.
 */
final readonly class DoctrineTransaction implements Transaction
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function run(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn(): mixed => $operation());
    }
}
