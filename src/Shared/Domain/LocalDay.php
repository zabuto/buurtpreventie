<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A calendar day on the local clock, from midnight to midnight. On the days the clock changes it lasts 23 or 25
 * hours, so it is never "24 hours from a UTC midnight".
 */
final readonly class LocalDay
{
    private function __construct(
        private DateTimeImmutable $localMidnight,
    ) {
    }

    public static function of(DateTimeImmutable $instant): self
    {
        return new self(LocalTime::of($instant)->setTime(0, 0));
    }

    /**
     * @return string Y-m-d
     */
    public function date(): string
    {
        return $this->localMidnight->format('Y-m-d');
    }

    public function next(): self
    {
        return new self($this->localMidnight->modify('+1 day'));
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->localMidnight->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The first instant after the day, which belongs to the next one.
     */
    public function endsAt(): DateTimeImmutable
    {
        return $this->next()->startsAt();
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $instant >= $this->startsAt() && $instant < $this->endsAt();
    }
}
