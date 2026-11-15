<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\LocalTime;
use Symfony\Component\Uid\Uuid;

/**
 * Decides who gets a reminder: each active walker once per local calendar day, for every round of that day they
 * have not been reminded of. Membership knows who is active, so the caller passes those members in.
 */
final readonly class Reminders
{
    /**
     * @param iterable<Round> $rounds
     * @param list<Uuid> $activeMemberIds
     *
     * @return list<Reminder> in the order of the rounds
     */
    public function due(iterable $rounds, array $activeMemberIds): array
    {
        $active = \array_map(static fn(Uuid $memberId): string => $memberId->toRfc4122(), $activeMemberIds);

        /** @var array<string, array{Uuid, list<Round>}> $due */
        $due = [];
        foreach ($rounds as $round) {
            foreach ($round->participationsToRemind() as $participation) {
                $memberId = $participation->memberId();
                if (!\in_array($memberId->toRfc4122(), $active, strict: true)) {
                    continue;
                }

                $key = $memberId->toRfc4122() . '|' . LocalTime::of($round->startsAt())->format('Y-m-d');
                $due[$key] ??= [$memberId, []];
                $due[$key][1][] = $round;
            }
        }

        return \array_values(\array_map(static fn(array $reminder): Reminder => new Reminder(...$reminder), $due));
    }
}
