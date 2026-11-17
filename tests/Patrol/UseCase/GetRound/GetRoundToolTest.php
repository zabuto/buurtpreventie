<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\GetRound;

use App\Shared\Domain\Role;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class GetRoundToolTest extends McpTestCase
{
    /**
     * A coordinator reads full names over REST, and a fellow walker the phone number of one who consented; over MCP
     * the walkers keep only id and display name.
     */
    #[Test]
    public function it_gives_the_rest_answer_without_the_full_name_and_phone_of_the_walkers(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member(Role::Walker, Role::Coordinator);
        self::token($jan);
        $round = self::round('2026-11-17T20:00:00+00:00', $jan, self::walker('Piet K.'));

        $described = self::call(self::connect($browser), 'get_round', ['roundId' => $round->id()->toRfc4122()]);

        $rest = self::rest($browser, '/api/v1/rounds/' . $round->id()->toRfc4122());
        self::assertIsArray($rest['walkers']);
        self::assertContains('Piet K. Fullname', self::column($rest['walkers'], 'name'));
        $rest['walkers'] = \array_map(
            static fn(mixed $walker): array => \is_array($walker) ? ['id' => $walker['id'], 'displayName' => $walker['displayName']] : [],
            $rest['walkers'],
        );
        self::assertSame($rest, $described);
    }

    #[Test]
    public function it_answers_a_round_that_does_not_exist_with_not_found(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $problem = self::failure(self::connect($browser), 'get_round', ['roundId' => '0196b0a0-0000-7000-8000-000000000000']);

        self::assertSame('urn:buurtpreventie:problem:not_found', $problem['type']);
    }
}
