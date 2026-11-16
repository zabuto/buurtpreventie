<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Fake;

use App\Patrol\Domain\Round\RoundReport;
use App\Patrol\Domain\Round\RoundReportRepository;
use App\Shared\Domain\NotFound;
use Override;
use Symfony\Component\Uid\Uuid;

/**
 * Finds a report through the rounds, which hold it.
 */
final readonly class InMemoryRoundReportRepository implements RoundReportRepository
{
    public function __construct(
        private InMemoryRoundRepository $rounds,
    ) {
    }

    #[Override]
    public function get(Uuid $id): RoundReport
    {
        foreach ($this->rounds->all() as $round) {
            $report = $round->report();
            if (null !== $report && $report->id()->equals($id)) {
                return $report;
            }
        }

        throw NotFound::forId('round report', $id);
    }

    #[Override]
    public function byAuthor(Uuid $authorId): array
    {
        $found = [];
        foreach ($this->rounds->all() as $round) {
            $report = $round->report();
            if (null !== $report && $report->authorId()->equals($authorId)) {
                $found[] = $report;
            }
        }
        \usort($found, static fn(RoundReport $a, RoundReport $b): int => $a->reportedAt() <=> $b->reportedAt());

        return $found;
    }
}
