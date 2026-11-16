<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\Transaction;
use Override;
use Throwable;

/**
 * Runs the operation at once and records the nesting depth of each run; whether a failed operation leaves no trace
 * is DoctrineTransaction's concern and has its own database test. Callbacks after the commit follow the same order as
 * there: after the outermost run, dropped when it fails.
 */
final class ImmediateTransaction implements Transaction
{
    /** @var list<int> depth of each run in start order, 1 for an outermost run */
    public array $depths = [];

    private int $depth = 0;

    /** @var list<callable(): void> */
    private array $afterCommit = [];

    #[Override]
    public function run(callable $operation): mixed
    {
        $this->depths[] = ++$this->depth;

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            if (1 === $this->depth) {
                $this->afterCommit = [];
            }

            throw $exception;
        } finally {
            --$this->depth;
        }

        if (0 === $this->depth) {
            $callbacks = $this->afterCommit;
            $this->afterCommit = [];
            foreach ($callbacks as $callback) {
                $callback();
            }
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
}
