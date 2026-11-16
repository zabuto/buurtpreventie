<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use DateTimeImmutable;

final readonly class ChainReport
{
    /**
     * @param ChainBreak|null $break the first place that does not hold; null for an intact chain
     * @param int $intactThrough position of the last entry that holds, or of the anchor when none does
     * @param DateTimeImmutable|null $intactThroughAt time of that entry; null for the anchor
     * @param int $checked entries after the anchor that hold
     * @param int $erased of those, entries whose changed fields were erased
     */
    public function __construct(
        public ?ChainBreak $break,
        public int $intactThrough,
        public ?DateTimeImmutable $intactThroughAt,
        public int $checked,
        public int $erased,
        public ChainLink $head,
    ) {
    }
}
