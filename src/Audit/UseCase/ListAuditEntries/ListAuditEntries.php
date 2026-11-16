<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListAuditEntries;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListAuditEntries
{
    /**
     * @param string|null $member the id of a member: what the member did and what was done to the member
     * @param string|null $from ISO 8601 with offset; entries from this moment on
     * @param string|null $to ISO 8601 with offset; entries before this moment
     * @param bool $allEntries every matching entry at once instead of a page, for a CSV file; the Accept header
     *                         decides, so the query string cannot set it
     */
    public function __construct(
        #[Assert\Uuid]
        public ?string $member = null,
        #[Assert\Length(max: 60)]
        public ?string $action = null,
        #[Assert\DateTime(format: \DATE_ATOM)]
        public ?string $from = null,
        #[Assert\DateTime(format: \DATE_ATOM)]
        public ?string $to = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Ignore]
        public bool $allEntries = false,
    ) {
    }
}
