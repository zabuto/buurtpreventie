<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

use App\Patrol\Domain\Round\WalkerRoundView;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListMyRounds
{
    /**
     * @param string $view upcoming (earliest first), to_report (walked without a report, latest first) or all
     * @param string|null $from ISO 8601 with offset; with to, rounds that start from this moment on; all needs both
     * @param string|null $to ISO 8601 with offset; rounds that start before this moment
     * @param string $order asc or desc, for the view all only
     */
    public function __construct(
        #[Assert\Choice(callback: [WalkerRoundView::class, 'values'])]
        public string $view = 'upcoming',
        #[Assert\DateTime(format: \DATE_ATOM)]
        public ?string $from = null,
        #[Assert\DateTime(format: \DATE_ATOM)]
        public ?string $to = null,
        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $order = 'asc',
        #[Assert\Range(min: 1, max: 200)]
        public int $limit = 50,
    ) {
    }
}
