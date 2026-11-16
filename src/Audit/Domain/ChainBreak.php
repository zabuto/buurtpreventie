<?php

declare(strict_types=1);

namespace App\Audit\Domain;

/**
 * The first place where the audit chain does not hold.
 */
final readonly class ChainBreak
{
    /**
     * @param int $position of the entry that does not hold, of the missing entry, or of the head
     */
    public function __construct(
        public ChainBreakReason $reason,
        public int $position,
    ) {
    }
}
