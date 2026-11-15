<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use DateTimeImmutable;

/**
 * Sends the one buddy call of a round to the active walkers who want it and do not walk the round.
 */
final readonly class BuddyCaller
{
    public function __construct(
        private MemberDirectory $members,
        private Notifier $notifier,
    ) {
    }

    /**
     * @return int|null the number of walkers called, or null when no call is due
     */
    public function callIfDue(Round $round, DateTimeImmutable $now): ?int
    {
        if (!BuddyCall::isNeeded($round, $now)) {
            return null;
        }

        $recipients = 0;
        foreach ($this->members->openRoundSubscribers() as $memberId) {
            if ($round->isSignedUp($memberId)) {
                continue;
            }

            $this->notifier->send(new Notification($memberId, 'round.buddy_needed', ['roundId' => $round->id()->toRfc4122()]));
            ++$recipients;
        }

        $round->markBuddyCallSent($now);

        return $recipients;
    }
}
