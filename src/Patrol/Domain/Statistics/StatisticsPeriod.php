<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Statistics;

use App\Patrol\Domain\Round\InvalidPeriod;
use App\Shared\Domain\LocalTime;
use DateTimeImmutable;

/**
 * The period the statistics count rounds in, by start, at most a year.
 */
final readonly class StatisticsPeriod
{
    /**
     * A leap year in local time; the hour the clocks change evens out over a year.
     */
    private const int MAXIMUM_DAYS = 366;

    /**
     * @throws InvalidPeriod for a period that does not end after its start or lasts longer than a year
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

    /**
     * The period to compare with: whole local months compare with as many months before, so October compares with
     * all of September; any other period with one just as long right before it.
     */
    public function previous(): self
    {
        $months = self::wholeMonths($this->from, $this->to);
        if (null !== $months) {
            return new self(LocalTime::of($this->from)->modify(\sprintf('-%d months', $months)), $this->from);
        }

        return new self($this->from->modify(\sprintf('-%d seconds', $this->to->getTimestamp() - $this->from->getTimestamp())), $this->from);
    }

    private static function wholeMonths(DateTimeImmutable $from, DateTimeImmutable $to): ?int
    {
        $localFrom = LocalTime::of($from);
        $localTo = LocalTime::of($to);
        if ('01 00:00:00' !== $localFrom->format('d H:i:s') || '01 00:00:00' !== $localTo->format('d H:i:s')) {
            return null;
        }

        return (((int) $localTo->format('Y') - (int) $localFrom->format('Y')) * 12) + (int) $localTo->format('n') - (int) $localFrom->format('n');
    }
}
