<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\ListAllApiTokens;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Identity\UseCase\ListAllApiTokens\ListAllApiTokens;
use App\Identity\UseCase\ListAllApiTokens\ListAllApiTokensHandler;
use App\Shared\Domain\AccessDenied;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedMemberNames;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * The admin sees every usable token, to spot one that does not belong, never its secret or hash.
 */
final class ListAllApiTokensHandlerTest extends TestCase
{
    private const string NOW = '2026-11-16T10:00:00+00:00';

    private InMemoryApiTokenRepository $tokens;
    private Uuid $janId;
    private Uuid $pietId;

    #[Override]
    protected function setUp(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->janId = Uuid::v7();
        $this->pietId = Uuid::v7();
    }

    #[Test]
    public function it_lists_each_active_token_with_its_member_without_the_secret_in_local_time(): void
    {
        $token = $this->token($this->janId, 'Claude Code', '2026-11-01T12:00:00+00:00');
        $token->markUsed(new DateTimeImmutable('2026-11-10T08:30:00+00:00'));

        $output = $this->handler()(new ListAllApiTokens());

        $this->assertSame(
            [[
                'id' => $token->id()->toRfc4122(),
                'memberId' => $this->janId->toRfc4122(),
                'member' => 'Jan B.',
                'name' => 'Claude Code',
                'scopes' => ['read', 'write'],
                'createdAt' => '2026-11-01T13:00:00+01:00',
                'expiresAt' => '2027-01-30T13:00:00+01:00',
                'lastUsedAt' => '2026-11-10T09:30:00+01:00',
            ]],
            $output->tokens,
        );
    }

    #[Test]
    public function it_lists_the_tokens_of_every_member_newest_first(): void
    {
        $this->token($this->janId, 'Old', '2026-10-01T12:00:00+00:00');
        $this->token($this->pietId, 'New', '2026-11-01T12:00:00+00:00');

        $output = $this->handler()(new ListAllApiTokens());

        $this->assertSame([['New', 'Piet K.'], ['Old', 'Jan B.']], \array_map(
            static fn(#[SensitiveParameter] array $token): array => [$token['name'], $token['member']],
            $output->tokens,
        ));
    }

    #[Test]
    public function it_leaves_out_revoked_and_expired_tokens(): void
    {
        $this->token($this->janId, 'Revoked', '2026-11-01T12:00:00+00:00')->revoke(new DateTimeImmutable('2026-11-02T12:00:00+00:00'));
        $this->token($this->janId, 'Expired', '2026-08-01T12:00:00+00:00');

        $output = $this->handler()(new ListAllApiTokens());

        $this->assertSame([], $output->tokens);
    }

    #[Test]
    public function it_lists_nothing_unless_the_actor_may_see_every_token(): void
    {
        $authorization = FakeAuthorization::refusing();
        $this->token($this->janId, 'Claude Code', '2026-11-01T12:00:00+00:00');

        $refusal = null;
        try {
            $this->handler($authorization)(new ListAllApiTokens());
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['api_token.list_all', null]], $authorization->asked);
    }

    private function handler(?FakeAuthorization $authorization = null): ListAllApiTokensHandler
    {
        return new ListAllApiTokensHandler(
            $this->tokens,
            new FixedMemberNames([$this->janId->toRfc4122() => 'Jan B.', $this->pietId->toRfc4122() => 'Piet K.']),
            $authorization ?? FakeAuthorization::granting(),
            new MockClock(self::NOW),
        );
    }

    /**
     * A token valid for 90 days from its creation.
     */
    private function token(Uuid $memberId, string $name, string $issuedAt): ApiToken
    {
        $issued = new DateTimeImmutable($issuedAt);
        $token = ApiToken::issue(
            $memberId,
            new TokenTerms($name, [TokenScope::Read, TokenScope::Write], $issued->modify('+90 days')),
            'bpt_' . \bin2hex(\random_bytes(32)),
            $issued,
        );
        $this->tokens->add($token);

        return $token;
    }
}
