<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\ListAuditEntries;

use App\Audit\UseCase\ListAuditEntries\ListAuditEntriesOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListAuditEntriesOutputTest extends TestCase
{
    private const array ENTRY = [
        'occurredAt' => '2026-11-14T13:00:00+01:00',
        'action' => 'member.changed',
        'actor' => 'member',
        'actorId' => '0193a3c0-0000-7000-8000-000000000001',
        'actorName' => 'Jan B.',
        'subjectType' => 'member',
        'subjectId' => '0193a3c0-0000-7000-8000-000000000002',
        'channel' => 'web',
        'changes' => ['certified' => ['old' => false, 'new' => true]],
    ];

    #[Test]
    public function it_writes_a_header_and_one_line_per_entry_in_a_fixed_order(): void
    {
        $csv = new ListAuditEntriesOutput([self::ENTRY], null)->csv();

        $this->assertSame(
            "occurredAt,action,actor,actorId,actorName,subjectType,subjectId,channel,changes\n"
                . '2026-11-14T13:00:00+01:00,member.changed,member,0193a3c0-0000-7000-8000-000000000001,"Jan B.",member,'
                . "0193a3c0-0000-7000-8000-000000000002,web,\"{\"\"certified\"\":{\"\"old\"\":false,\"\"new\"\":true}}\"\n",
            $csv,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formulas(): iterable
    {
        yield 'equals' => ['=HYPERLINK("http://example.org")'];
        yield 'plus' => ['+1'];
        yield 'minus' => ['-1'];
        yield 'at' => ['@SUM(A1)'];
        yield 'tab' => ["\t=1"];
        yield 'carriage return' => ["\r=1"];
    }

    /**
     * A display name is typed by a member, and a spreadsheet would run it as a formula.
     */
    #[DataProvider('formulas')]
    #[Test]
    public function it_neutralises_a_cell_that_a_spreadsheet_would_read_as_a_formula(string $name): void
    {
        $csv = new ListAuditEntriesOutput([[...self::ENTRY, 'actorName' => $name]], null)->csv();

        $line = \str_getcsv(\explode("\n", $csv, 3)[1], escape: '');
        $this->assertSame("'" . $name, $line[4] ?? null);
    }
}
