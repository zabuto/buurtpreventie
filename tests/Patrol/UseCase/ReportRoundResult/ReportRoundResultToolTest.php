<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ReportRoundResult;

use App\Identity\Domain\TokenScope;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ReportRoundResultToolTest extends McpTestCase
{
    #[Test]
    public function it_reports_a_walked_round_like_rest_on_the_mcp_channel(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        $outcome = self::outcome();
        $overMcp = self::round('2026-11-14T20:00:00+00:00', $jan, self::walker('Piet K.'));
        $overRest = self::round('2026-11-13T20:00:00+00:00', $jan, self::walker('Kees V.'));

        $reported = self::call(self::connect($browser), 'report_round_result', [
            'roundId' => $overMcp->id()->toRfc4122(),
            'outcomeId' => $outcome->id()->toRfc4122(),
            'note' => 'Rustig.',
        ]);
        self::restWrite($browser, 'POST', '/api/v1/rounds/' . $overRest->id()->toRfc4122() . '/reports', ['outcomeId' => $outcome->id()->toRfc4122()]);

        self::assertSame($overMcp->report()?->id()->toRfc4122(), $reported['id'] ?? null);
        self::assertSame('Rustig.', $overMcp->report()?->note());
        self::assertSame(
            [
                ['report.submitted', 'mcp'],
                ['report.submitted', 'api'],
            ],
            self::audited(),
        );
    }

    #[Test]
    public function it_refuses_a_token_that_may_only_read(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan, TokenScope::Read);
        $round = self::round('2026-11-14T20:00:00+00:00', $jan, self::walker('Piet K.'));

        $problem = self::failure(self::connect($browser), 'report_round_result', [
            'roundId' => $round->id()->toRfc4122(),
            'outcomeId' => self::outcome()->id()->toRfc4122(),
        ]);

        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', $problem['type']);
        self::assertNull($round->report());
    }

    #[Test]
    public function it_refuses_a_walker_who_did_not_walk_the_round(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        $round = self::round('2026-11-14T20:00:00+00:00', self::walker('Piet K.'), self::walker('Kees V.'));

        $problem = self::failure(self::connect($browser), 'report_round_result', [
            'roundId' => $round->id()->toRfc4122(),
            'outcomeId' => self::outcome()->id()->toRfc4122(),
        ]);

        self::assertSame('urn:buurtpreventie:problem:access_denied', $problem['type']);
        self::assertNull($round->report());
    }

    private static function outcome(): Outcome
    {
        $outcome = Outcome::create('Opmerkingen', OutcomeKind::Remarks);
        self::service(InMemoryOutcomeRepository::class)->add($outcome);

        return $outcome;
    }
}
