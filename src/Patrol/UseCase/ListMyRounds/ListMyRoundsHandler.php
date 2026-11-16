<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\RoundStatus;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Lists the rounds the member acting walks or walked, by start; the walked ones tell whether they have a report, so
 * the member sees what is left to report.
 */
#[ReadAction]
final readonly class ListMyRoundsHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private ExecutionContext $executionContext,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AccessDenied when nobody acts
     */
    public function __invoke(ListMyRounds $input): ListMyRoundsOutput
    {
        $memberId = $this->executionContext->actor()->memberId ?? throw AccessDenied::forAction('round.list_own');

        $now = $this->clock->now();
        $rounds = \array_filter(
            $this->rounds->withWalker($memberId),
            static fn(Round $round): bool => null === $input->walked || (RoundStatus::Walked === $round->status($now)) === $input->walked,
        );

        return new ListMyRoundsOutput(\array_values(\array_map(static fn(Round $round): array => self::summary($round, $now), $rounds)));
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
     *     openSpots: int,
     *     meetingPoint: string|null,
     *     reported: bool,
     * }
     */
    private static function summary(Round $round, DateTimeImmutable $now): array
    {
        return [
            'id' => $round->id()->toRfc4122(),
            'startsAt' => LocalTime::of($round->startsAt())->format(\DATE_ATOM),
            'daypart' => $round->daypart()->value,
            'status' => $round->status($now)->value,
            'walkerCount' => $round->walkerCount(),
            'minimumWalkers' => $round->staffingRule()->minimumWalkers,
            'maximumWalkers' => $round->staffingRule()->maximumWalkers,
            'openSpots' => $round->openSpots($now),
            'meetingPoint' => $round->meetingPoint()?->name(),
            'reported' => null !== $round->report(),
        ];
    }
}
