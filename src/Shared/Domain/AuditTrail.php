<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * The append-only audit log, implemented by the Audit module. Every write handler records its action inside the
 * same Transaction as the change, so the log never holds an action that was rolled back, nor misses one that stuck.
 */
interface AuditTrail
{
    public function record(AuditedAction $action): void;
}
