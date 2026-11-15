<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\Transaction;
use Override;

/**
 * Runs the operation at once and counts the runs; whether a failed operation leaves no trace is DoctrineTransaction's
 * concern and has its own database test.
 */
final class ImmediateTransaction implements Transaction
{
    public int $runs = 0;

    #[Override]
    public function run(callable $operation): mixed
    {
        ++$this->runs;

        return $operation();
    }
}
