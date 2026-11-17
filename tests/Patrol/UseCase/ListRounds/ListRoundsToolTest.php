<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListRounds;

use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ListRoundsToolTest extends McpTestCase
{
    #[Test]
    public function it_gives_the_same_rounds_as_the_rest_endpoint(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        self::round('2026-11-17T20:00:00+00:00', $jan, self::walker('Piet K.'));
        self::round('2026-11-18T20:00:00+00:00', $jan);
        $period = ['from' => '2026-11-16T00:00:00+01:00', 'to' => '2026-11-23T00:00:00+01:00'];

        $rounds = self::call(self::connect($browser), 'list_rounds', $period);

        self::assertCount(2, (array) ($rounds['rounds'] ?? []));
        self::assertSame(self::rest($browser, '/api/v1/rounds', $period), $rounds);
    }

    #[Test]
    public function it_refuses_a_period_in_another_format_on_the_field(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $problem = self::failure(self::connect($browser), 'list_rounds', ['from' => 'next week', 'to' => '2026-11-23T00:00:00+01:00']);

        self::assertSame('urn:buurtpreventie:problem:invalid_input', $problem['type']);
        self::assertSame(['from'], \array_column((array) ($problem['violations'] ?? []), 'field'));
    }
}
