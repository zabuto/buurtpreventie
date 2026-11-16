<?php

declare(strict_types=1);

namespace App\Audit\UseCase\VerifyAuditChain;

final readonly class VerifyAuditChainOutput
{
    /**
     * @param int $intactThrough position of the last entry that holds, or of the anchor when none does
     * @param string|null $intactThroughAt time of that entry on the local clock; null for the anchor
     * @param int $checked entries after the anchor that hold
     * @param int $erased of those, entries whose changed fields were erased with the member
     * @param int $beforeChain entries from before the chain, which have no hash and are not checked
     * @param int $lastPosition the position the head names, worth noting down: the chain cannot show that someone
     *                          removed the newest entries and set the head back to an earlier one
     * @param string|null $breakReason changed_content, missing_entry, inserted_or_relinked or head_mismatch
     * @param int|null $breakPosition of the entry that does not hold, of the missing entry, or of the head
     */
    public function __construct(
        public bool $intact,
        public int $intactThrough,
        public ?string $intactThroughAt,
        public int $checked,
        public int $erased,
        public int $beforeChain,
        public int $lastPosition,
        public ?string $breakReason,
        public ?int $breakPosition,
    ) {
    }
}
