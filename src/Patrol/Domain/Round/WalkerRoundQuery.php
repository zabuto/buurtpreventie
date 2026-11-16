<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * The rounds a member is signed up for, in one view, optionally within a period, at most a limit. Walked follows
 * RoundStatus: started, staffed and not cancelled.
 */
final readonly class WalkerRoundQuery
{
    /**
     * @param ?ListingPeriod $period by the start of the round
     */
    public function __construct(
        public Uuid $memberId,
        public WalkerRoundView $view,
        public DateTimeImmutable $now,
        public ?ListingPeriod $period,
        public bool $newestFirst,
        public int $limit,
    ) {
    }

    public function matches(Round $round): bool
    {
        if (!$round->isSignedUp($this->memberId)) {
            return false;
        }

        if (null !== $this->period && ($round->startsAt() < $this->period->from || $round->startsAt() >= $this->period->to)) {
            return false;
        }

        return match ($this->view) {
            WalkerRoundView::Upcoming => !$round->hasStarted($this->now),
            WalkerRoundView::ToReport => RoundStatus::Walked === $round->status($this->now) && null === $round->report(),
            WalkerRoundView::All => true,
        };
    }
}
