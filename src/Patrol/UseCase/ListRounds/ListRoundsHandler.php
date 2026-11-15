<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRounds;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundRepository;
use App\Patrol\Domain\RoundStatus;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/**
 * Lists the rounds that start in a period, by start, optionally with one status only.
 */
#[ReadAction]
final readonly class ListRoundsHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private Authorization $authorization,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidArgumentException for a period that does not end after its start
     */
    public function __invoke(ListRounds $input): ListRoundsOutput
    {
        $this->authorization->denyUnlessGranted('round.list');

        $from = new DateTimeImmutable($input->from);
        $to = new DateTimeImmutable($input->to);
        if ($to <= $from) {
            throw new InvalidArgumentException(\sprintf('The period from %s does not end after its start.', $input->from));
        }

        $now = $this->clock->now();
        $status = null === $input->status ? null : RoundStatus::from($input->status);
        $rounds = \array_filter(
            $this->rounds->between($from, $to),
            static fn(Round $round): bool => null === $status || $round->status($now) === $status,
        );

        return new ListRoundsOutput(\array_values(\array_map(static fn(Round $round): array => self::summary($round, $now), $rounds)));
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
        ];
    }
}
