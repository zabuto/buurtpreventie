<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeRound;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\MeetingPointArchived;
use App\Patrol\Domain\MeetingPointRepository;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundRepository;
use App\Patrol\Domain\RoundStart;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'round.change', audit: 'round.changed')]
final readonly class ChangeRoundHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private MeetingPointRepository $meetingPoints,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeRound $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $round = $this->rounds->get(Uuid::fromString($input->roundId));
            $this->authorization->denyUnlessGranted('round.change', $round);

            $before = self::fields($round);
            $round->change(
                new RoundStart(new DateTimeImmutable($input->startsAt), $this->meetingPoint($round, $input->meetingPointId), $input->startNote),
                $this->clock->now(),
            );

            $changes = [];
            foreach (self::fields($round) as $field => $value) {
                if ($value !== $before[$field]) {
                    $changes[$field] = ['old' => $before[$field], 'new' => $value];
                }
            }

            if ([] !== $changes) {
                $this->auditTrail->record(new AuditedAction('round.changed', 'round', $round->id(), $changes));
            }
        });
    }

    /**
     * An archived meeting point is not offered for a new start, but a round keeps the one it has.
     */
    private function meetingPoint(Round $round, ?string $id): ?MeetingPoint
    {
        if (null === $id) {
            return null;
        }

        $meetingPoint = $this->meetingPoints->get(Uuid::fromString($id));
        if ($meetingPoint->isArchived() && $meetingPoint !== $round->meetingPoint()) {
            throw MeetingPointArchived::forItem($meetingPoint->id());
        }

        return $meetingPoint;
    }

    /**
     * @return array{startsAt: string, meetingPoint: string|null, startNote: string|null}
     */
    private static function fields(Round $round): array
    {
        return [
            'startsAt' => $round->startsAt()->format(\DATE_ATOM),
            'meetingPoint' => $round->meetingPoint()?->id()->toRfc4122(),
            'startNote' => $round->startNote(),
        ];
    }
}
