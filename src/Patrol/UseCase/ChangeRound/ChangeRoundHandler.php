<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeRound;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\RoundStart;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\Unchanged;
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
                new RoundStart(
                    $input->startsAt instanceof Unchanged ? $round->startsAt() : new DateTimeImmutable($input->startsAt),
                    $input->meetingPointId instanceof Unchanged ? $round->meetingPoint() : $this->meetingPoint($input->meetingPointId),
                    $input->startNote instanceof Unchanged ? $round->startNote() : $input->startNote,
                ),
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

    private function meetingPoint(?string $id): ?MeetingPoint
    {
        if (null === $id) {
            return null;
        }

        return $this->meetingPoints->get(Uuid::fromString($id));
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
