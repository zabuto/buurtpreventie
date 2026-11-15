<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * Walkers of an upcoming round must know where to meet, so its meeting point stays available until the round
 * starts or moves elsewhere.
 */
final class MeetingPointInUse extends DomainException
{
    public static function byUpcomingRounds(Uuid $meetingPointId, int $rounds): self
    {
        return new self(\sprintf('Meeting point %s is where %d upcoming round(s) start.', $meetingPointId->toRfc4122(), $rounds));
    }
}
