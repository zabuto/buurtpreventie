<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListAuditEntries;

final readonly class ListAuditEntriesOutput
{
    private const array COLUMNS = ['occurredAt', 'action', 'actor', 'actorId', 'actorName', 'subjectType', 'subjectId', 'channel', 'changes'];

    /**
     * A spreadsheet reads a cell that starts with one of these as a formula.
     */
    private const string FORMULA_STARTS = "=+-@\t\r";

    /**
     * @param iterable<int, array{
     *     occurredAt: string,
     *     action: string,
     *     actor: string,
     *     actorId: string|null,
     *     actorName: string|null,
     *     subjectType: string,
     *     subjectId: string,
     *     channel: string,
     *     changes: array<string, array{old: scalar|null, new: scalar|null}>,
     * }> $entries newest first, a list for a page and read lazily for every entry at once; actor is member,
     *      removed_member (erased, without a name) or system
     */
    public function __construct(
        public iterable $entries,
        public ?int $nextPage,
    ) {
    }

    /**
     * Writes the entries as CSV with a header, columns in a fixed order, changes as JSON, and every cell a spreadsheet
     * would run as a formula prefixed with an apostrophe.
     *
     * @param resource $file
     */
    public function writeCsv($file): void
    {
        \fputcsv($file, self::COLUMNS, escape: '');
        foreach ($this->entries as $entry) {
            $cells = [...$entry, 'changes' => \json_encode($entry['changes'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)];
            \fputcsv(
                $file,
                \array_map(
                    static fn(string $column): string => self::neutralised((string) $cells[$column]),
                    self::COLUMNS,
                ),
                escape: '',
            );
        }
    }

    private static function neutralised(string $cell): string
    {
        return '' !== $cell && \str_contains(self::FORMULA_STARTS, $cell[0]) ? "'" . $cell : $cell;
    }
}
