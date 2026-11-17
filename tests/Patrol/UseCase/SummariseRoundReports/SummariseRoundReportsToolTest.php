<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\SummariseRoundReports;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Shared\Domain\Role;
use App\Tests\Support\McpTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class SummariseRoundReportsToolTest extends McpTestCase
{
    private const array PERIOD = ['from' => '2026-11-01T00:00:00+01:00', 'to' => '2026-12-01T00:00:00+01:00'];

    #[Test]
    public function it_gives_the_same_summary_as_the_rest_endpoint(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Analyst));
        $piet = self::walker('Piet K.');
        $round = self::round('2026-11-12T20:00:00+00:00', $piet, self::walker('Kees V.'));
        $round->submitReport($piet->id(), Outcome::create('Inbraak', OutcomeKind::Incident), 'Raam open.', new DateTimeImmutable('2026-11-13T08:00:00+00:00'));

        $summary = self::call(self::connect($browser), 'report_summary', self::PERIOD);

        self::assertSame(1, $summary['incidents']);
        self::assertSame(self::rest($browser, '/api/v1/reports/summary', self::PERIOD), $summary);
    }

    #[Test]
    public function it_refuses_a_member_who_neither_analyses_nor_coordinates(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Walker));

        $problem = self::failure(self::connect($browser), 'report_summary', self::PERIOD);

        self::assertSame('urn:buurtpreventie:problem:access_denied', $problem['type']);
    }
}
