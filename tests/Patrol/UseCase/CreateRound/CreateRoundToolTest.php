<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CreateRound;

use App\Identity\Domain\TokenScope;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

#[Group('functional')]
final class CreateRoundToolTest extends McpTestCase
{
    #[Test]
    public function it_plans_a_round_with_the_member_as_first_walker_like_rest_on_the_mcp_channel(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);

        $created = self::call(self::connect($browser), 'create_round', ['startsAt' => '2026-11-20T21:00:00+01:00', 'startNote' => 'Bij de speeltuin']);
        self::restWrite($browser, 'POST', '/api/v1/rounds', ['startsAt' => '2026-11-21T21:00:00+01:00']);

        self::assertSame(['id'], \array_keys($created));
        self::assertIsString($created['id']);
        $round = self::service(InMemoryRoundRepository::class)->get(Uuid::fromString($created['id']));
        self::assertTrue($round->isSignedUp($jan->id()));
        self::assertSame('Bij de speeltuin', $round->startNote());
        self::assertSame(
            [
                ['round.created', 'mcp'],
                ['round.created', 'api'],
            ],
            self::audited(),
        );
    }

    #[Test]
    public function it_refuses_a_token_that_may_only_read(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(), TokenScope::Read);

        $problem = self::failure(self::connect($browser), 'create_round', ['startsAt' => '2026-11-20T21:00:00+01:00']);

        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', $problem['type']);
        self::assertSame([], self::audited());
    }

    #[Test]
    public function it_refuses_a_member_who_does_not_walk(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member(Role::Analyst));

        $problem = self::failure(self::connect($browser), 'create_round', ['startsAt' => '2026-11-20T21:00:00+01:00']);

        self::assertSame('urn:buurtpreventie:problem:access_denied', $problem['type']);
    }

    #[Test]
    public function it_refuses_a_start_note_of_more_than_500_characters(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());

        $problem = self::failure(self::connect($browser), 'create_round', ['startsAt' => '2026-11-20T21:00:00+01:00', 'startNote' => \str_repeat('a', 501)]);

        self::assertSame(['startNote'], \array_column((array) ($problem['violations'] ?? []), 'field'));
    }
}
