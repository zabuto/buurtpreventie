<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Throwable;

/**
 * One unit of work: a change and its audit entry are stored together or not at all.
 */
interface Transaction
{
    /**
     * Runs the operation and commits everything it changed; when the operation throws, nothing is stored and the
     * exception reaches the caller unchanged.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @throws Throwable whatever the operation throws
     *
     * @return T
     */
    public function run(callable $operation): mixed;

    /**
     * Runs the callback once the outermost run has committed, in the order of the calls; when that run fails, the
     * callback never runs. Outside a run it runs at once. For what must not happen for a change that is undone, such
     * as a mail about it.
     *
     * @param callable(): void $callback
     */
    public function afterCommit(callable $callback): void;
}
