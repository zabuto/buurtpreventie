<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SummariseRoundReports;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SummariseRoundReports
{
    /**
     * @param string $from ISO 8601 with offset; reports of rounds that start from this moment on
     * @param string $to ISO 8601 with offset; rounds that start before this moment, at most 366 days after from
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $from,
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $to,
    ) {
    }
}
