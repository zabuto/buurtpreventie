<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use DateTimeImmutable;
use DateTimeZone;

/**
 * When and where a round starts: the instant in UTC, an optional meeting point, and an optional note such as where
 * exactly to meet.
 */
final readonly class RoundStart
{
    public DateTimeImmutable $startsAt;
    public ?string $note;

    public function __construct(
        DateTimeImmutable $startsAt,
        public ?MeetingPoint $meetingPoint = null,
        ?string $note = null,
    ) {
        $note = \trim($note ?? '');

        $this->startsAt = $startsAt->setTimezone(new DateTimeZone('UTC'));
        $this->note = '' === $note ? null : $note;
    }

    /**
     * @throws StartInPast for a start at or before this moment
     */
    public function refuseUnlessAfter(DateTimeImmutable $now): void
    {
        if ($this->startsAt <= $now) {
            throw StartInPast::at($this->startsAt);
        }
    }
}
