<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\Transaction;
use Override;

/**
 * Runs the operation at once and records the nesting depth of each run; whether a failed operation leaves no trace
 * is DoctrineTransaction's concern and has its own database test.
 */
final class ImmediateTransaction implements Transaction
{
    /** @var list<int> depth of each run in start order, 1 for an outermost run */
    public array $depths = [];

    private int $depth = 0;

    #[Override]
    public function run(callable $operation): mixed
    {
        $this->depths[] = ++$this->depth;

        try {
            return $operation();
        } finally {
            --$this->depth;
        }
    }
}
