<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\AddRoundComment;

use App\Identity\Domain\TokenScope;
use App\Patrol\Domain\Round\RoundComment;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class AddRoundCommentToolTest extends McpTestCase
{
    #[Test]
    public function it_adds_a_comment_like_rest_on_the_mcp_channel(): void
    {
        $browser = self::mcpBrowser();
        $jan = self::member();
        self::token($jan);
        $round = self::round('2026-11-17T20:00:00+00:00', $jan);

        $added = self::call(self::connect($browser), 'add_round_comment', ['roundId' => $round->id()->toRfc4122(), 'text' => 'Ik neem een zaklamp mee.']);
        self::restWrite($browser, 'POST', '/api/v1/rounds/' . $round->id()->toRfc4122() . '/comments', ['text' => 'Tot dan.']);

        self::assertSame(['id'], \array_keys($added));
        self::assertSame(
            ['Ik neem een zaklamp mee.', 'Tot dan.'],
            \array_map(
                static fn(RoundComment $comment): string => $comment->text(),
                self::service(InMemoryRoundCommentRepository::class)->forRound($round),
            ),
        );
        self::assertSame(
            [
                ['comment.added', 'mcp'],
                ['comment.added', 'api'],
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
        $round = self::round('2026-11-17T20:00:00+00:00', $jan);

        $problem = self::failure(self::connect($browser), 'add_round_comment', ['roundId' => $round->id()->toRfc4122(), 'text' => 'Hallo']);

        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', $problem['type']);
    }

    #[Test]
    public function it_refuses_a_walker_of_another_round(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        $round = self::round('2026-11-17T20:00:00+00:00', self::walker('Piet K.'));

        $problem = self::failure(self::connect($browser), 'add_round_comment', ['roundId' => $round->id()->toRfc4122(), 'text' => 'Hallo']);

        self::assertSame('urn:buurtpreventie:problem:access_denied', $problem['type']);
        self::assertSame([], self::audited());
    }
}
