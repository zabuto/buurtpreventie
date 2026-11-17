<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RoundStatistics;

use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Statistics\RoundTally;
use App\Patrol\Domain\Statistics\StatisticsGrouping;
use App\Patrol\Domain\Statistics\StatisticsPeriod;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Counts how the rounds of a period went, per local month, weekday or daypart, next to the period just before. The
 * status of a round is derived, so the rounds are counted here rather than in a query; a year holds a few hundred.
 * Never per walker: the statistics are about the neighbourhood, not about who walks least.
 */
#[ReadAction]
final readonly class RoundStatisticsHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidPeriod
     */
    public function __invoke(RoundStatistics $input): RoundStatisticsOutput
    {
        $this->authorization->denyUnlessGranted('round.statistics');

        $period = new StatisticsPeriod(new DateTimeImmutable($input->from), new DateTimeImmutable($input->to));
        $grouping = StatisticsGrouping::from($input->groupBy);
        $now = $this->clock->now();
        $rounds = $this->rounds->between($period->from, $period->to);

        $grouped = \array_fill_keys($grouping->groupsIn($period), []);
        foreach ($rounds as $round) {
            $grouped[$grouping->groupOf($round->startsAt())][] = $round;
        }

        $previous = $period->previous();

        return new RoundStatisticsOutput(
            from: LocalTime::of($period->from)->format(\DATE_ATOM),
            to: LocalTime::of($period->to)->format(\DATE_ATOM),
            groupBy: $grouping->value,
            groups: \array_map(
                static fn(string $group, array $members): array => ['group' => $group, ...RoundTally::of($now, ...$members)->toArray()],
                \array_keys($grouped),
                $grouped,
            ),
            total: RoundTally::of($now, ...$rounds)->toArray(),
            previous: [
                'from' => LocalTime::of($previous->from)->format(\DATE_ATOM),
                'to' => LocalTime::of($previous->to)->format(\DATE_ATOM),
                ...RoundTally::of($now, ...$this->rounds->between($previous->from, $previous->to))->toArray(),
            ],
        );
    }
}
