<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SummariseRoundReports;

use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundReport;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Statistics\StatisticsGrouping;
use App\Patrol\Domain\Statistics\StatisticsPeriod;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;

/**
 * Counts the reports of the rounds in a period per outcome, and the incidents per local month, for an overview such
 * as one for the police. Counts only: no note and no author, so the summary names nobody.
 */
#[ReadAction]
final readonly class SummariseRoundReportsHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
    ) {
    }

    /**
     * @throws InvalidPeriod
     */
    public function __invoke(SummariseRoundReports $input): SummariseRoundReportsOutput
    {
        $this->authorization->denyUnlessGranted('report.list');

        $period = new StatisticsPeriod(new DateTimeImmutable($input->from), new DateTimeImmutable($input->to));
        $reports = \array_values(\array_filter(\array_map(
            static fn(Round $round): ?RoundReport => $round->report(),
            $this->rounds->between($period->from, $period->to),
        )));

        $outcomes = [];
        $incidents = \array_fill_keys(StatisticsGrouping::Month->groupsIn($period), 0);
        foreach ($reports as $report) {
            $outcome = $report->outcome();
            $key = $outcome->id()->toRfc4122();
            $outcomes[$key] ??= ['outcomeId' => $key, 'outcome' => $outcome->name(), 'outcomeKind' => $outcome->kind()->value, 'count' => 0];
            ++$outcomes[$key]['count'];
            if (OutcomeKind::Incident === $outcome->kind()) {
                ++$incidents[StatisticsGrouping::Month->groupOf($report->round()->startsAt())];
            }
        }

        \usort($outcomes, static fn(array $a, array $b): int => [$b['count'], $a['outcome']] <=> [$a['count'], $b['outcome']]);

        return new SummariseRoundReportsOutput(
            from: LocalTime::of($period->from)->format(\DATE_ATOM),
            to: LocalTime::of($period->to)->format(\DATE_ATOM),
            reports: \count($reports),
            incidents: \array_sum($incidents),
            outcomes: $outcomes,
            incidentsByMonth: \array_map(
                static fn(string $month, int $count): array => ['month' => $month, 'incidents' => $count],
                \array_keys($incidents),
                $incidents,
            ),
        );
    }
}
