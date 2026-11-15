<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ApiTokenTest extends TestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret whose hash the test knows
    private const string SECRET = 'bpt_vast-geheim';
    private const string ISSUED_AT = '2026-11-15T12:00:00+00:00';

    #[Test]
    public function it_stores_only_the_sha256_hash_of_its_secret(): void
    {
        $token = $this->token();

        $this->assertSame('fac41e241871f17901f7b612f10e1ea3f4ea388638fec79d2543c0c10c840996', \bin2hex($token->secretHash()));
    }

    #[Test]
    public function it_matches_only_the_secret_it_was_issued_with(): void
    {
        $token = $this->token();

        $this->assertSame([true, false], [$token->matches(self::SECRET), $token->matches('bpt_ander-geheim')]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function momentsAroundTheExpiry(): iterable
    {
        yield 'a second before' => ['2026-12-31T22:59:59+00:00', true];
        yield 'at the expiry' => ['2026-12-31T23:00:00+00:00', false];
    }

    #[DataProvider('momentsAroundTheExpiry')]
    #[Test]
    public function it_is_usable_until_it_expires(string $now, bool $expected): void
    {
        $token = $this->token(expiresAt: new DateTimeImmutable('2026-12-31T23:00:00+00:00'));

        $this->assertSame($expected, $token->isUsableAt(new DateTimeImmutable($now)));
    }

    #[Test]
    public function it_is_usable_without_an_expiry(): void
    {
        $token = $this->token();

        $this->assertTrue($token->isUsableAt(new DateTimeImmutable('2036-11-15T12:00:00+00:00')));
    }

    #[Test]
    public function it_is_unusable_once_revoked(): void
    {
        $token = $this->token();

        $token->revoke(new DateTimeImmutable('2027-01-01T00:00:00+00:00'));

        $this->assertFalse($token->isUsableAt(new DateTimeImmutable('2027-01-01T00:00:00+00:00')));
    }

    #[Test]
    public function it_keeps_the_moment_it_was_first_revoked(): void
    {
        $token = $this->token();
        $token->revoke(new DateTimeImmutable('2027-01-01T00:00:00+00:00'));

        $token->revoke(new DateTimeImmutable('2027-02-01T00:00:00+00:00'));

        $this->assertEquals(new DateTimeImmutable('2027-01-01T00:00:00+00:00'), $token->revokedAt());
    }

    #[Test]
    public function it_keeps_each_scope_once(): void
    {
        $terms = new TokenTerms('Script', [TokenScope::Read, TokenScope::Write, TokenScope::Read]);

        $token = ApiToken::issue(Uuid::v7(), $terms, self::SECRET, new DateTimeImmutable(self::ISSUED_AT));

        $this->assertSame([TokenScope::Read, TokenScope::Write], $token->scopes());
    }

    private function token(?DateTimeImmutable $expiresAt = null): ApiToken
    {
        return ApiToken::issue(Uuid::v7(), new TokenTerms('Script', [TokenScope::Read], $expiresAt), self::SECRET, new DateTimeImmutable(self::ISSUED_AT));
    }

    #[Test]
    public function it_remembers_when_it_was_last_used(): void
    {
        $token = $this->token();

        $token->markUsed(new DateTimeImmutable('2026-11-16T08:00:00+00:00'));

        $this->assertEquals(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), $token->lastUsedAt());
    }
}
