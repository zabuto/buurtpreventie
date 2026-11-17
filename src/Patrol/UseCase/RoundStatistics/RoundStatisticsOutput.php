<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RoundStatistics;

final readonly class RoundStatisticsOutput
{
    /**
     * Every count is a number of rounds, except incidents: reports with an outcome of the kind incident. A percentage
     * counts the rounds that are over (walked, not walked and cancelled), rounded to one decimal, and is null when
     * none are.
     *
     * @param list<array{
     *     group: string,
     *     planned: int,
     *     walked: int,
     *     notWalked: int,
     *     cancelled: int,
     *     upcoming: int,
     *     incidents: int,
     *     walkedPercentage: float|null,
     *     notWalkedPercentage: float|null,
     * }> $groups every month, weekday (monday first) or daypart (morning first) of the period, also without rounds
     * @param array{
     *     planned: int,
     *     walked: int,
     *     notWalked: int,
     *     cancelled: int,
     *     upcoming: int,
     *     incidents: int,
     *     walkedPercentage: float|null,
     *     notWalkedPercentage: float|null,
     * } $total
     * @param array{
     *     from: string,
     *     to: string,
     *     planned: int,
     *     walked: int,
     *     notWalked: int,
     *     cancelled: int,
     *     upcoming: int,
     *     incidents: int,
     *     walkedPercentage: float|null,
     *     notWalkedPercentage: float|null,
     * } $previous the same counts for the period to compare with: as many whole months before, or one just as long
     *            right before
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $groupBy,
        public array $groups,
        public array $total,
        public array $previous,
    ) {
    }
}
