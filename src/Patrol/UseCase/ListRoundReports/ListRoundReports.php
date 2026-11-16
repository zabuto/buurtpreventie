<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRoundReports;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListRoundReports
{
    /**
     * @param string $from ISO 8601 with offset; rounds that start from here
     * @param string $to ISO 8601 with offset; rounds that start before here, at most 93 days after from
     * @param string|null $outcome the id of the one outcome to list
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $from,
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $to,
        #[Assert\Uuid]
        public ?string $outcome = null,
    ) {
    }
}
