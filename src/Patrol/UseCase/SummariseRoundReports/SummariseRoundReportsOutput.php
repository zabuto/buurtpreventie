<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SummariseRoundReports;

final readonly class SummariseRoundReportsOutput
{
    /**
     * @param int $reports the reports of the rounds that start in the period
     * @param int $incidents the reports with an outcome of the kind incident
     * @param list<array{outcomeId: string, outcome: string, outcomeKind: string, count: int}> $outcomes each outcome
     *     reported at least once, most reported first
     * @param list<array{month: string, incidents: int}> $incidentsByMonth every local month of the period, also
     *     without incidents
     */
    public function __construct(
        public string $from,
        public string $to,
        public int $reports,
        public int $incidents,
        public array $outcomes,
        public array $incidentsByMonth,
    ) {
    }
}
