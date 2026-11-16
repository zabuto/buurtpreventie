<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRoundReports;

use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Round\ListingPeriod;
use App\Patrol\Domain\Round\RoundReport;
use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Lists the reports of the rounds that start in a period, by start of the round, for analysts and coordinators; notes
 * included, since both may read every note.
 */
#[ReadAction]
final readonly class ListRoundReportsHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private MemberDirectory $memberDirectory,
        private Authorization $authorization,
    ) {
    }

    /**
     * @throws InvalidPeriod for a period that does not end after its start or lasts longer than 93 days
     */
    public function __invoke(ListRoundReports $input): ListRoundReportsOutput
    {
        $this->authorization->denyUnlessGranted('report.list');

        $period = new ListingPeriod(new DateTimeImmutable($input->from), new DateTimeImmutable($input->to));
        $outcome = null === $input->outcome ? null : Uuid::fromString($input->outcome);
        $reports = [];
        foreach ($this->rounds->between($period->from, $period->to) as $round) {
            $report = $round->report();
            if (null === $report || null !== $outcome && !$report->outcome()->id()->equals($outcome)) {
                continue;
            }

            $reports[] = $report;
        }

        $authors = $this->memberDirectory->profilesOf(\array_map(static fn(RoundReport $report): Uuid => $report->authorId(), $reports));

        return new ListRoundReportsOutput(\array_map(static fn(RoundReport $report): array => self::line($report, $authors), $reports));
    }

    /**
     * @param array<string, MemberProfile> $authors
     *
     * @return array{
     *     id: string,
     *     roundId: string,
     *     roundStartsAt: string,
     *     daypart: string,
     *     meetingPoint: string|null,
     *     walkerCount: int,
     *     outcomeId: string,
     *     outcome: string,
     *     outcomeKind: string,
     *     note: string|null,
     *     authorDisplayName: string|null,
     *     reportedAt: string,
     * }
     */
    private static function line(RoundReport $report, array $authors): array
    {
        $round = $report->round();

        return [
            'id' => $report->id()->toRfc4122(),
            'roundId' => $round->id()->toRfc4122(),
            'roundStartsAt' => LocalTime::of($round->startsAt())->format(\DATE_ATOM),
            'daypart' => $round->daypart()->value,
            'meetingPoint' => $round->meetingPoint()?->name(),
            'walkerCount' => $round->walkerCount(),
            'outcomeId' => $report->outcome()->id()->toRfc4122(),
            'outcome' => $report->outcome()->name(),
            'outcomeKind' => $report->outcome()->kind()->value,
            'note' => $report->note(),
            'authorDisplayName' => $authors[$report->authorId()->toRfc4122()]->displayName ?? null,
            'reportedAt' => LocalTime::of($report->reportedAt())->format(\DATE_ATOM),
        ];
    }
}
