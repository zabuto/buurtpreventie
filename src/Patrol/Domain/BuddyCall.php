<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\LocalTime;
use DateTimeImmutable;
use DateTimeZone;

/**
 * When walkers who want to hear of open rounds get their one message about a round that seeks a buddy. Walkers first
 * get time to sign up by themselves, so the message waits for the first of 5, 3 or 1 days before the start that falls
 * after the creation; a round created less than a day ahead is announced at once.
 */
final readonly class BuddyCall
{
    private const array LEAD_DAYS = [5, 3, 1];

    /**
     * Lead days count on the local clock, so the message keeps its time of day across a clock change.
     */
    public static function dueAt(DateTimeImmutable $createdAt, DateTimeImmutable $startsAt): DateTimeImmutable
    {
        $localStart = LocalTime::of($startsAt);
        foreach (self::LEAD_DAYS as $leadDays) {
            $due = $localStart->modify(\sprintf('-%d days', $leadDays))->setTimezone(new DateTimeZone('UTC'));
            if ($due > $createdAt) {
                return $due;
            }
        }

        return $createdAt;
    }

    /**
     * Whether the call goes out at this moment: once per round, only when due, before the start, and while the round
     * seeks a buddy. A buddy who signs up and then withdraws causes no second call.
     */
    public static function isNeeded(Round $round, DateTimeImmutable $now): bool
    {
        return (
            null === $round->buddyCallSentAt()
                && $now >= $round->buddyCallDueAt()
                && RoundStatus::SeekingBuddy === $round->status($now)
        );
    }
}
