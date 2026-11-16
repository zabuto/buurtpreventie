<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\ListApiTokens;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Identity\UseCase\ListApiTokens\ListApiTokens;
use App\Identity\UseCase\ListApiTokens\ListApiTokensHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Shared\Fake\FixedExecutionContext;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ListApiTokensHandlerTest extends TestCase
{
    private InMemoryApiTokenRepository $tokens;
    private Uuid $memberId;

    #[Override]
    protected function setUp(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->memberId = Uuid::v7();
    }

    #[Test]
    public function it_lists_the_tokens_of_the_member_acting_without_their_secrets_in_local_time(): void
    {
        $token = $this->token($this->memberId, 'Agenda', '2026-11-01T12:00:00+00:00');
        $token->markUsed(new DateTimeImmutable('2026-11-10T08:30:00+00:00'));

        $output = $this->handler()(new ListApiTokens());

        $this->assertSame(
            [[
                'id' => $token->id()->toRfc4122(),
                'name' => 'Agenda',
                'scopes' => ['read'],
                'createdAt' => '2026-11-01T13:00:00+01:00',
                'expiresAt' => '2027-01-30T13:00:00+01:00',
                'lastUsedAt' => '2026-11-10T09:30:00+01:00',
            ]],
            $output->tokens,
        );
    }

    #[Test]
    public function it_lists_the_newest_token_first(): void
    {
        $this->token($this->memberId, 'Oud', '2026-10-01T12:00:00+00:00');
        $this->token($this->memberId, 'Nieuw', '2026-11-01T12:00:00+00:00');

        $output = $this->handler()(new ListApiTokens());

        $this->assertSame(['Nieuw', 'Oud'], \array_column($output->tokens, 'name'));
    }

    #[Test]
    public function it_leaves_out_revoked_tokens_and_tokens_of_other_members(): void
    {
        $this->token($this->memberId, 'Ingetrokken', '2026-11-01T12:00:00+00:00')->revoke(new DateTimeImmutable('2026-11-02T12:00:00+00:00'));
        $this->token(Uuid::v7(), 'Van een ander', '2026-11-01T12:00:00+00:00');

        $output = $this->handler()(new ListApiTokens());

        $this->assertSame([], $output->tokens);
    }

    #[Test]
    public function it_refuses_when_nobody_acts(): void
    {
        $this->expectException(AccessDenied::class);

        new ListApiTokensHandler($this->tokens, new FixedExecutionContext())(new ListApiTokens());
    }

    private function token(Uuid $memberId, string $name, string $issuedAt): ApiToken
    {
        $issued = new DateTimeImmutable($issuedAt);
        $token = ApiToken::issue(
            $memberId,
            new TokenTerms($name, [TokenScope::Read], $issued->modify('+90 days')),
            'bpt_' . \bin2hex(\random_bytes(32)),
            $issued,
        );
        $this->tokens->add($token);

        return $token;
    }

    private function handler(): ListApiTokensHandler
    {
        return new ListApiTokensHandler($this->tokens, new FixedExecutionContext(new Actor($this->memberId, [])));
    }
}
