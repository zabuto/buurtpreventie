<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\WithdrawFromRound;

use App\Identity\Domain\TokenScope;
use App\Shared\Domain\Role;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class WithdrawFromRoundToolTest extends McpTestCase
{
    #[Test]
    public function it_withdraws_the_member_with_the_same_audit_entry_as_rest_on_the_mcp_channel(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        $overMcp = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'), $jan);
        $overRest = self::round('2026-11-18T20:00:00+00:00', self::walker('Kees V.'), $jan);

        $result = self::call(self::connect($browser), 'withdraw_from_round', ['roundId' => $overMcp->id()->toRfc4122()]);
        self::restWrite($browser, 'DELETE', '/api/v1/rounds/' . $overRest->id()->toRfc4122() . '/walkers/me');

        self::assertSame(['roundId' => $overMcp->id()->toRfc4122(), 'withdrawn' => true], $result);
        self::assertFalse($overMcp->isSignedUp($jan->id()));
        self::assertSame(
            [
                ['participation.withdrawn', 'mcp'],
                ['participation.withdrawn', 'api'],
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
        $round = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'), $jan);

        $problem = self::failure(self::connect($browser), 'withdraw_from_round', ['roundId' => $round->id()->toRfc4122()]);

        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', $problem['type']);
        self::assertTrue($round->isSignedUp($jan->id()));
    }

    #[Test]
    public function it_refuses_a_member_who_does_not_walk(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Analyst));
        $round = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'));

        $problem = self::failure(self::connect($browser), 'withdraw_from_round', ['roundId' => $round->id()->toRfc4122()]);

        self::assertSame('urn:buurtpreventie:problem:access_denied', $problem['type']);
    }
}
