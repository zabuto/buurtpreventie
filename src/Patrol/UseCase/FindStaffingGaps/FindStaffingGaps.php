<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\FindStaffingGaps;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FindStaffingGaps
{
    /**
     * @param int|null $days only the rounds that start within this many days from now; null for every upcoming round
     */
    public function __construct(
        #[Assert\Range(min: 1, max: 31)]
        public ?int $days = null,
    ) {
    }
}
