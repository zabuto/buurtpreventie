<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * How many walkers a round needs and allows. Fixed when the round is created, so a changed setting never rewrites
 * whether a past round was walked.
 */
#[ORM\Embeddable]
final readonly class StaffingRule
{
    /**
     * @throws InvalidArgumentException when the minimum is below one or the maximum below the minimum
     */
    public function __construct(
        #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
        public int $minimumWalkers,
        #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
        public int $maximumWalkers,
    ) {
        if ($minimumWalkers < 1) {
            throw new InvalidArgumentException(\sprintf('A round needs at least one walker, not %d.', $minimumWalkers));
        }

        if ($maximumWalkers < $minimumWalkers) {
            throw new InvalidArgumentException(\sprintf(
                'The maximum of %d walkers is below the minimum of %d.',
                $maximumWalkers,
                $minimumWalkers,
            ));
        }
    }

    public function isStaffedBy(int $walkers): bool
    {
        return $walkers >= $this->minimumWalkers;
    }

    public function isFullWith(int $walkers): bool
    {
        return $walkers >= $this->maximumWalkers;
    }

    public function openSpotsFor(int $walkers): int
    {
        return \max(0, $this->maximumWalkers - $walkers);
    }
}
