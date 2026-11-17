<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RoundStatistics;

use App\Patrol\Domain\Statistics\StatisticsGrouping;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RoundStatistics
{
    /**
     * @param string $from ISO 8601 with offset; rounds that start from this moment on
     * @param string $to ISO 8601 with offset; rounds that start before this moment, at most 366 days after from
     * @param string $groupBy month, weekday or daypart, in local time
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $from,
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $to,
        #[Assert\Choice(callback: [StatisticsGrouping::class, 'values'])]
        public string $groupBy = 'month',
    ) {
    }
}
