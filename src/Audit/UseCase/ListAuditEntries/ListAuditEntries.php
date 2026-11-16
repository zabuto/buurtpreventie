<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListAuditEntries;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListAuditEntries
{
    /**
     * @param string|null $member the id of a member: what the member did and what was done to the member
     * @param string|null $from ISO 8601 with offset; entries from this moment on
     * @param string|null $to ISO 8601 with offset; entries before this moment
     * @param string $format json for a page, csv for every matching entry at once
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
        #[Assert\Choice(choices: ['json', 'csv'])]
        public string $format = 'json',
    ) {
    }
}
