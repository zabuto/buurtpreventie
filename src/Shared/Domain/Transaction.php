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
}
