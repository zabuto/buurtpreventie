<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListAuditEntries;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Audit\Domain\AuditFilter;
use App\Audit\Domain\TooManyEntries;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Generator;
use Symfony\Component\Uid\Uuid;

/**
 * The whole audit log for the admin, newest first, narrowed to a member, an action and a period: a page at a time,
 * or every matching entry at once for a CSV file, read lazily. IP addresses in it are shortened when recorded.
 */
#[ReadAction]
final readonly class ListAuditEntriesHandler
{
    public const int PAGE_SIZE = 50;

    public const int MOST_ENTRIES_AT_ONCE = 10_000;

    /**
     * Read in batches, so a CSV file never holds every entity of the log in memory at once.
     */
    private const int BATCH_SIZE = 500;

    public function __construct(
        private AuditEntryRepository $entries,
        private Authorization $authorization,
    ) {
    }

    /**
     * @throws TooManyEntries for every entry at once when more match than one file may hold
     */
    public function __invoke(ListAuditEntries $input): ListAuditEntriesOutput
    {
        $this->authorization->denyUnlessGranted('audit.view');

        $filter = new AuditFilter(
            null === $input->member ? null : Uuid::fromString($input->member),
            null === $input->action || '' === $input->action ? null : $input->action,
            null === $input->from ? null : new DateTimeImmutable($input->from),
            null === $input->to ? null : new DateTimeImmutable($input->to),
        );

        if ($input->allEntries) {
            $count = $this->entries->countMatching($filter);
            if ($count > self::MOST_ENTRIES_AT_ONCE) {
                throw TooManyEntries::counted($count, self::MOST_ENTRIES_AT_ONCE);
            }

            return new ListAuditEntriesOutput($this->everyLine($filter), null);
        }

        $page = \max(1, $input->page);
        $entries = $this->entries->matching($filter, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE + 1);

        return new ListAuditEntriesOutput(
            \array_map(self::line(...), \array_slice($entries, 0, self::PAGE_SIZE)),
            \count($entries) > self::PAGE_SIZE ? $page + 1 : null,
        );
    }

    /**
     * @return Generator<int, array{
     *     occurredAt: string,
     *     action: string,
     *     actor: string,
     *     actorId: string|null,
     *     actorName: string|null,
     *     subjectType: string,
     *     subjectId: string,
     *     channel: string,
     *     changes: array<string, array{old: scalar|null, new: scalar|null}>,
     * }>
     */
    private function everyLine(AuditFilter $filter): Generator
    {
        $offset = 0;
        do {
            $batch = $this->entries->matching($filter, $offset, self::BATCH_SIZE);
            foreach ($batch as $entry) {
                yield self::line($entry);
            }
            $offset += self::BATCH_SIZE;
        } while (\count($batch) === self::BATCH_SIZE);
    }

    /**
     * @return array{
     *     occurredAt: string,
     *     action: string,
     *     actor: string,
     *     actorId: string|null,
     *     actorName: string|null,
     *     subjectType: string,
     *     subjectId: string,
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
            'subjectType' => $entry->subjectType(),
            'subjectId' => $entry->subjectId()->toRfc4122(),
            'channel' => $entry->channel()->value,
            'changes' => $entry->changes(),
        ];
    }
}
