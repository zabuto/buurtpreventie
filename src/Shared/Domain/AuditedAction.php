<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * What a handler hands the audit trail. Time, actor and channel come from the clock and the execution context, so
 * no handler can record them wrongly.
 */
final readonly class AuditedAction
{
    /**
     * @param string $action such as round.created or participation.withdrawn
     * @param string $subjectType such as round or member
     * @param array<string, array{old: scalar|null, new: scalar|null}> $changes field name => old and new value;
     *                                                                         never a password or token
     */
    public function __construct(
        public string $action,
        public string $subjectType,
        public Uuid $subjectId,
        public array $changes = [],
    ) {
    }
}
