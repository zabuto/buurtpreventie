<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListMyRounds;

use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ListMyRoundsToolTest extends McpTestCase
{
    #[Test]
    public function it_gives_the_same_own_rounds_as_the_rest_endpoint(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        self::round('2026-11-17T20:00:00+00:00', $jan);
        self::round('2026-11-18T20:00:00+00:00', self::walker('Piet K.'));

        $rounds = self::call(self::connect($browser), 'my_rounds');

        self::assertCount(1, (array) ($rounds['rounds'] ?? []));
        self::assertSame(self::rest($browser, '/api/v1/me/rounds'), $rounds);
    }

    #[Test]
    public function it_gives_the_walked_rounds_that_wait_for_a_report(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        $walked = self::round('2026-11-14T20:00:00+00:00', $jan, self::walker('Piet K.'));
        self::round('2026-11-17T20:00:00+00:00', $jan);

        $rounds = self::call(self::connect($browser), 'my_rounds', ['view' => 'to_report']);

        self::assertSame([$walked->id()->toRfc4122()], \array_column((array) ($rounds['rounds'] ?? []), 'id'));
        self::assertSame(self::rest($browser, '/api/v1/me/rounds', ['view' => 'to_report']), $rounds);
    }

    #[Test]
    public function it_refuses_a_view_it_does_not_know(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $problem = self::failure(self::connect($browser), 'my_rounds', ['view' => 'everything']);

        self::assertSame(['view'], \array_column((array) ($problem['violations'] ?? []), 'field'));
    }
}
