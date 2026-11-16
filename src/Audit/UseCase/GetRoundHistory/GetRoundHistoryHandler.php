<?php

declare(strict_types=1);

namespace App\Audit\UseCase\GetRoundHistory;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\ReadAction;
use Symfony\Component\Uid\Uuid;

/**
 * The audit entries about one round, for a coordinator: who planned, changed, joined, left, reported or commented.
 * It reads the log only, so the history of a deleted round stays readable.
 */
#[ReadAction]
final readonly class GetRoundHistoryHandler
{
    public function __construct(
        private AuditEntryRepository $entries,
        private Authorization $authorization,
    ) {
    }

    /**
     * @throws NotFound for a round the log knows nothing about
     */
    public function __invoke(GetRoundHistory $input): GetRoundHistoryOutput
    {
        $this->authorization->denyUnlessGranted('audit.view_round_history');

        $roundId = Uuid::fromString($input->roundId);
        $entries = $this->entries->bySubject('round', $roundId);
        if ([] === $entries) {
            throw NotFound::forId('round', $roundId);
        }

        return new GetRoundHistoryOutput(\array_map(self::line(...), $entries));
    }

    /**
     * @return array{
     *     occurredAt: string,
     *     action: string,
     *     actor: string,
     *     actorId: string|null,
     *     actorName: string|null,
     *     channel: string,
     *     changes: array<string, array{old: scalar|null, new: scalar|null}>,
     * }
     */
    private static function line(AuditEntry $entry): array
    {
        return [
            'occurredAt' => LocalTime::of($entry->occurredAt())->format(\DATE_ATOM),
            'action' => $entry->action(),
            'actor' => match (true) {
                $entry->isBySystem() => 'system',
                null === $entry->actorName() => 'removed_member',
                default => 'member',
            },
            'actorId' => $entry->actorId()?->toRfc4122(),
            'actorName' => $entry->actorName(),
            'channel' => $entry->channel()->value,
            'changes' => $entry->changes(),
        ];
    }
}
