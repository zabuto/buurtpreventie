<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\FindStaffingGaps;

use App\Identity\Domain\TokenScope;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Support\McpTestCase;
use DateTimeImmutable;
use Mcp\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

#[Group('functional')]
final class FindStaffingGapsToolTest extends McpTestCase
{
    #[Test]
    public function it_gives_the_same_gaps_as_the_rest_endpoint(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        self::round('2026-11-17T20:00:00+00:00');
        self::round('2026-11-25T20:00:00+00:00');

        $gaps = self::call(self::connect($browser), 'staffing_gaps', ['days' => 3]);
        $browser->request('GET', '/api/v1/staffing-gaps?days=3', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        self::assertCount(1, (array) ($gaps['gaps'] ?? []));
        self::assertSame(self::json($browser), $gaps);
    }

    #[Test]
    public function it_refuses_more_than_31_days_as_invalid_input_on_the_field(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $problem = self::failure(self::connect($browser), 'staffing_gaps', ['days' => 32]);

        self::assertSame('urn:buurtpreventie:problem:invalid_input', $problem['type']);
        self::assertSame([['field' => 'days', 'message' => 'This value should be between 1 and 31.']], $problem['violations'] ?? null);
    }

    /**
     * Every member may read the gaps, so the scope is what can refuse it: without read the server lets no call in.
     */
    #[Test]
    public function it_refuses_a_token_without_the_read_scope(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(), TokenScope::Write);

        $refusal = null;
        try {
            self::connect($browser);
        } catch (ConnectionException $exception) {
            $refusal = $exception;
        }

        self::assertNotNull($refusal);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', self::json($browser)['type']);
    }

    private static function round(string $startsAt): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        self::service(InMemoryRoundRepository::class)->add($round);

        return $round;
    }
}
