<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

use App\Patrol\Domain\Round\RoundRepository;
use DateTimeImmutable;

/**
 * Keeps a meeting point on the list while rounds that have not started meet there, so their walkers keep a place
 * to meet that everyone can still pick.
 */
final readonly class MeetingPointInUseGuard
{
    public function __construct(
        private RoundRepository $rounds,
    ) {
    }

    /**
     * @throws MeetingPointInUse
     */
    public function refuseWhileInUse(MeetingPoint $meetingPoint, DateTimeImmutable $now): void
    {
        $upcoming = $this->rounds->countUpcomingAt($meetingPoint, $now);
        if ($upcoming > 0) {
            throw MeetingPointInUse::byUpcomingRounds($meetingPoint->id(), $upcoming);
        }
    }
}
