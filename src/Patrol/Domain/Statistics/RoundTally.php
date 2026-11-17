<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Statistics;

use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStatus;
use DateTimeImmutable;

/**
 * How a set of rounds went: walked, not walked for want of a buddy, cancelled, still to come, and the incidents
 * reported. Percentages count the rounds that are over, so a period still running is not skewed by its future.
 */
final readonly class RoundTally
{
    private function __construct(
        private int $planned,
        private int $walked,
        private int $notWalked,
        private int $cancelled,
        private int $incidents,
    ) {
    }

    public static function of(DateTimeImmutable $now, Round ...$rounds): self
    {
        $statuses = \array_map(static fn(Round $round): RoundStatus => $round->status($now), $rounds);
        $count = static fn(RoundStatus $status): int => \count(\array_filter($statuses, static fn(RoundStatus $of): bool => $status === $of));

        return new self(
            planned: \count($rounds),
            walked: $count(RoundStatus::Walked),
            notWalked: $count(RoundStatus::NotWalked),
            cancelled: $count(RoundStatus::Cancelled),
            incidents: \count(\array_filter($rounds, static fn(Round $round): bool => OutcomeKind::Incident === $round->report()?->outcome()->kind())),
        );
    }

    /**
     * @return array{
     *     planned: int,
     *     walked: int,
     *     notWalked: int,
     *     cancelled: int,
     *     upcoming: int,
     *     incidents: int,
     *     walkedPercentage: float|null,
     *     notWalkedPercentage: float|null,
     * }
     */
    public function toArray(): array
    {
        $over = $this->walked + $this->notWalked + $this->cancelled;

        return [
            'planned' => $this->planned,
            'walked' => $this->walked,
            'notWalked' => $this->notWalked,
            'cancelled' => $this->cancelled,
            'upcoming' => $this->planned - $over,
            'incidents' => $this->incidents,
            'walkedPercentage' => self::percentage($this->walked, $over),
            'notWalkedPercentage' => self::percentage($this->notWalked, $over),
        ];
    }

    private static function percentage(int $part, int $whole): ?float
    {
        return 0 === $whole ? null : \round(($part * 100) / $whole, 1);
    }
}
