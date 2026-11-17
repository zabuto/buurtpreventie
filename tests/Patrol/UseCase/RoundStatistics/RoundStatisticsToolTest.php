<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\RoundStatistics;

use App\Shared\Domain\Role;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class RoundStatisticsToolTest extends McpTestCase
{
    private const array PERIOD = ['from' => '2026-10-01T00:00:00+02:00', 'to' => '2026-12-01T00:00:00+01:00', 'groupBy' => 'weekday'];

    #[Test]
    public function it_gives_the_same_statistics_as_the_rest_endpoint(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Analyst));
        self::round('2026-11-12T20:00:00+00:00', self::walker('Piet K.'), self::walker('Kees V.'));

        $statistics = self::call(self::connect($browser), 'round_statistics', self::PERIOD);

        self::assertSame(1, \is_array($statistics['total']) ? $statistics['total']['walked'] : null);
        self::assertSame(self::rest($browser, '/api/v1/stats', self::PERIOD), $statistics);
    }

    #[Test]
    public function it_refuses_a_member_who_neither_analyses_nor_coordinates(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Walker));

        $problem = self::failure(self::connect($browser), 'round_statistics', self::PERIOD);

        self::assertSame('urn:buurtpreventie:problem:access_denied', $problem['type']);
    }

    #[Test]
    public function it_refuses_a_period_of_more_than_a_year_with_its_rule(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Coordinator));

        $problem = self::failure(self::connect($browser), 'round_statistics', ['from' => '2026-01-01T00:00:00+01:00', 'to' => '2027-03-01T00:00:00+01:00']);

        self::assertSame('urn:buurtpreventie:problem:invalid_period', $problem['type']);
    }
}
