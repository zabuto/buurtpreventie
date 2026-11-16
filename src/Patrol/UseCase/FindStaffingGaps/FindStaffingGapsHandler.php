<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\FindStaffingGaps;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\RoundStatus;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Finds the rounds that have not started and lack walkers, by start. A full round is never a gap: its maximum is
 * never below its minimum.
 */
#[ReadAction]
final readonly class FindStaffingGapsHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(FindStaffingGaps $input): FindStaffingGapsOutput
    {
        $this->authorization->denyUnlessGranted('round.list');

        $now = $this->clock->now();
        $gaps = \array_filter(
            $this->rounds->upcoming($now),
            static fn(Round $round): bool => RoundStatus::SeekingBuddy === $round->status($now),
        );

        return new FindStaffingGapsOutput(\array_values(\array_map(static fn(Round $round): array => self::gap($round, $now), $gaps)));
    }

    /**
     * @return array{
     *     id: string,
     *     startsAt: string,
     *     daypart: string,
     *     status: string,
     *     walkerCount: int,
     *     minimumWalkers: int,
     *     maximumWalkers: int,
     *     walkersNeeded: int,
     *     openSpots: int,
     *     meetingPoint: string|null,
     * }
     */
    private static function gap(Round $round, DateTimeImmutable $now): array
    {
        $minimum = $round->staffingRule()->minimumWalkers;

        return [
            'id' => $round->id()->toRfc4122(),
            'startsAt' => LocalTime::of($round->startsAt())->format(\DATE_ATOM),
            'daypart' => $round->daypart()->value,
            'status' => $round->status($now)->value,
            'walkerCount' => $round->walkerCount(),
            'minimumWalkers' => $minimum,
            'maximumWalkers' => $round->staffingRule()->maximumWalkers,
            'walkersNeeded' => $minimum - $round->walkerCount(),
            'openSpots' => $round->openSpots($now),
            'meetingPoint' => $round->meetingPoint()?->name(),
        ];
    }
}
