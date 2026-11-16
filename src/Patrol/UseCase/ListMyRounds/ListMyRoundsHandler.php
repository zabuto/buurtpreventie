<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Round\ListingPeriod;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\WalkerRoundQuery;
use App\Patrol\Domain\Round\WalkerRoundView;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Lists the rounds the member acting walks or walked, in one view: the ones still to walk, the walked ones without a
 * report, or every round of a period. Each round tells whether it has a report, so the member sees what is left to
 * report.
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
     * @throws InvalidPeriod for half a period, one over 93 days, or every round without a period
     */
    public function __invoke(ListMyRounds $input): ListMyRoundsOutput
    {
        $memberId = $this->executionContext->actor()->memberId ?? throw AccessDenied::forAction('round.list_own');

        $view = WalkerRoundView::from($input->view);
        $period = self::period($input);
        if (WalkerRoundView::All === $view && null === $period) {
            throw new InvalidPeriod('Every round of a member needs a period.');
        }

        $now = $this->clock->now();
        $rounds = $this->rounds->forWalker(new WalkerRoundQuery(
            $memberId,
            $view,
            $now,
            $period,
            match ($view) {
                WalkerRoundView::Upcoming => false,
                WalkerRoundView::ToReport => true,
                WalkerRoundView::All => 'desc' === $input->order,
            },
            $input->limit,
        ));

        return new ListMyRoundsOutput(\array_map(static fn(Round $round): array => self::summary($round, $now), $rounds));
    }

    /**
     * @throws InvalidPeriod for a start without an end or the other way round
     */
    private static function period(ListMyRounds $input): ?ListingPeriod
    {
        if (null === $input->from && null === $input->to) {
            return null;
        }

        if (null === $input->from || null === $input->to) {
            throw new InvalidPeriod('A period needs both a start and an end.');
        }

        return new ListingPeriod(new DateTimeImmutable($input->from), new DateTimeImmutable($input->to));
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
