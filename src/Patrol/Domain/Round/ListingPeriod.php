<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use DateTimeImmutable;

/**
 * The period a list of rounds or reports covers: it ends after its start and lasts at most 93 days.
 */
final readonly class ListingPeriod
{
    /**
     * A quarter in local time, the hour the clocks go back included, keeps one answer to a few hundred rounds.
     */
    private const int MAXIMUM_DAYS = 93;

    /**
     * @throws InvalidPeriod for a period that does not end after its start or lasts longer than the maximum
     */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
        if ($to <= $from) {
            throw new InvalidPeriod(\sprintf('The period from %s does not end after its start.', $from->format(\DATE_ATOM)));
        }

        if (($to->getTimestamp() - $from->getTimestamp()) > (self::MAXIMUM_DAYS * 86_400)) {
            throw new InvalidPeriod(\sprintf('The period from %s lasts longer than %d days.', $from->format(\DATE_ATOM), self::MAXIMUM_DAYS));
        }
    }
}
