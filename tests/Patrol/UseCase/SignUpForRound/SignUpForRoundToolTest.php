<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\SignUpForRound;

use App\Identity\Domain\TokenScope;
use App\Shared\Domain\Role;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class SignUpForRoundToolTest extends McpTestCase
{
    #[Test]
    public function it_signs_the_member_up_with_the_same_audit_entry_as_rest_on_the_mcp_channel(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        $overMcp = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'));
        $overRest = self::round('2026-11-18T20:00:00+00:00', self::walker('Kees V.'));

        $result = self::call(self::connect($browser), 'sign_up_for_round', ['roundId' => $overMcp->id()->toRfc4122()]);
        self::restWrite($browser, 'PUT', '/api/v1/rounds/' . $overRest->id()->toRfc4122() . '/walkers/me');

        self::assertSame(['roundId' => $overMcp->id()->toRfc4122(), 'signedUp' => true], $result);
        self::assertTrue($overMcp->isSignedUp($jan->id()));
        self::assertSame(
            [
                ['participation.signed_up', 'mcp'],
                ['participation.signed_up', 'api'],
            ],
            self::audited(),
        );
    }

    #[Test]
    public function it_changes_nothing_when_the_member_signs_up_twice(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        $round = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'));
        $client = self::connect($browser);

        self::call($client, 'sign_up_for_round', ['roundId' => $round->id()->toRfc4122()]);
        self::call($client, 'sign_up_for_round', ['roundId' => $round->id()->toRfc4122()]);

        self::assertSame(2, $round->walkerCount());
        self::assertCount(1, self::audited());
    }

    #[Test]
    public function it_refuses_a_token_that_may_only_read(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan, TokenScope::Read);
        $round = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'));

        $problem = self::failure(self::connect($browser), 'sign_up_for_round', ['roundId' => $round->id()->toRfc4122()]);

        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', $problem['type']);
        self::assertFalse($round->isSignedUp($jan->id()));
    }

    #[Test]
    public function it_refuses_a_member_who_does_not_walk(): void
    {
        $browser = self::mcpBrowser();
        $analyst = self::member(Role::Analyst);
        self::token($analyst);
        $round = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'));

        $problem = self::failure(self::connect($browser), 'sign_up_for_round', ['roundId' => $round->id()->toRfc4122()]);

        self::assertSame('urn:buurtpreventie:problem:access_denied', $problem['type']);
        self::assertSame([], self::audited());
    }

    #[Test]
    public function it_refuses_a_full_round_with_its_rule(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        $round = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'), self::walker('Kees V.'), self::walker('Anna D.'));

        $problem = self::failure(self::connect($browser), 'sign_up_for_round', ['roundId' => $round->id()->toRfc4122()]);

        self::assertSame('urn:buurtpreventie:problem:round_is_full', $problem['type']);
    }
}
