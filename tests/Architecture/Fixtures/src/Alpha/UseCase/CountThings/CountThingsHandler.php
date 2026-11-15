<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\CountThings;

use Doctrine\DBAL\Connection;

/** Violates the input and handler rule: a handler queries through Doctrine DBAL instead of a repository interface. */
final class CountThingsHandler
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(CountThings $input): bool
    {
        return $this->connection->isTransactionActive();
    }
}
