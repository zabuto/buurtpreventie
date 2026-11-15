<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use DateTimeImmutable;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * The rounds of the demo, from three months back to six weeks ahead: most days an evening round, now and then one
 * in the morning or afternoon.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class DemoRounds
{
    public const int FIRST_DAY = -91;
    private const int LAST_DAY = 42;
    private const array EVENING = ['20:00', '20:30', '21:00', '22:00'];
    private const array DAYTIME = ['10:00', '14:30'];

    public function __construct(
        private PatrolSteps $patrol,
    ) {
    }

    public function play(Randomizer $random, DateTimeImmutable $now, Neighbourhood $neighbourhood): void
    {
        $index = 0;
        for ($day = self::FIRST_DAY; $day <= self::LAST_DAY; ++$day) {
            $evening = $random->getFloat(0, 1) < 0.6;
            $eveningTime = self::EVENING[$random->getInt(0, \count(self::EVENING) - 1)];
            $daytime = $random->getFloat(0, 1) < 0.1;
            $daytimeTime = self::DAYTIME[$random->getInt(0, \count(self::DAYTIME) - 1)];

            if ($evening) {
                $this->round(DemoRound::draw($random, $index++, $neighbourhood), SampleCalendar::day($now, $day, $eveningTime), $now, $neighbourhood);
            }
            // A round earlier today may lie before or after now, which would make the pattern depend on the hour.
            if ($daytime && 0 !== $day) {
                $this->round(DemoRound::draw($random, $index++, $neighbourhood), SampleCalendar::day($now, $day, $daytimeTime), $now, $neighbourhood);
            }
        }
    }

    /**
     * Creates the round and lets the buddies sign up between its creation and its start, or now if that comes first.
     */
    private function round(DemoRound $round, DateTimeImmutable $startsAt, DateTimeImmutable $now, Neighbourhood $neighbourhood): void
    {
        $createdAt = \min($startsAt->modify(\sprintf('-%d days', $round->leadDays)), $now->modify(\sprintf('-%d hours', $round->leadDays)));
        $lastSignUp = \min($startsAt->modify('-1 hour'), $now);
        $step = \intdiv($lastSignUp->getTimestamp() - $createdAt->getTimestamp(), \count($round->buddies) + 2);
        $moment = static fn(float $steps): DateTimeImmutable => $createdAt->modify(\sprintf('+%d seconds', (int) ($step * $steps)));

        $id = $round->byCoordinator
            ? $this->patrol->createRound($neighbourhood->coordinator, $createdAt, $startsAt, $round->meetingPoint, $round->owner)
            : $this->patrol->createRound($round->owner, $createdAt, $startsAt, $round->meetingPoint);
        foreach ($round->buddies as $position => $buddy) {
            $this->patrol->signUp($buddy, $moment($position + 1), $id);
        }
        if (null !== $round->comment) {
            $this->patrol->comment($round->buddies[0] ?? $round->owner, $moment(\count($round->buddies) + 1), $id, $round->comment);
        }
        if (null !== $round->leaver) {
            $this->patrol->signUp($round->leaver, $moment(0.5), $id);
            $this->patrol->withdraw($round->leaver, $moment(1.5), $id);
        }

        if ([] !== $round->buddies && $startsAt->modify('+3 hours') <= $now) {
            $this->finish($round, $id, $startsAt, $neighbourhood);
        }
    }

    /**
     * A walked round gets its report, or now and then a cancellation after the start instead.
     */
    private function finish(DemoRound $round, string $id, DateTimeImmutable $startsAt, Neighbourhood $neighbourhood): void
    {
        if ($round->cancelled) {
            $this->patrol->cancel($round->owner, $startsAt->modify('+20 minutes'), $id, 'Onweer, we zijn niet gegaan.');

            return;
        }
        if ($round->reported) {
            $this->patrol->report($round->reporter, $startsAt->modify('+2 hours'), $id, $neighbourhood->outcomes[$round->outcome], $round->note);
        }
    }
}
