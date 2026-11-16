<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\InvalidTokenExpiry;
use App\Identity\Domain\TokenRefusal;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final class ApiTokenTest extends TestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret whose hash the test knows
    private const string SECRET = 'bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const string ISSUED_AT = '2026-11-15T12:00:00+00:00';

    #[Test]
    public function it_stores_only_the_sha256_hash_of_its_secret(): void
    {
        $token = $this->token();

        $this->assertSame('a2296723ecba63a3f18102a065f80303719e4d4ca85f602d8d28b54f77e160f7', \bin2hex($token->secretHash()));
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function expiriesOutsideAYear(): iterable
    {
        yield 'at the moment of issue' => ['2026-11-15T12:00:00+00:00'];
        yield 'before the moment of issue' => ['2026-11-14T12:00:00+00:00'];
        yield 'a second after 365 days' => ['2027-11-15T12:00:01+00:00'];
    }

    #[DataProvider('expiriesOutsideAYear')]
    #[Test]
    public function it_refuses_an_expiry_that_is_not_within_365_days_after_issue(string $expiresAt): void
    {
        $this->expectException(InvalidTokenExpiry::class);

        $this->token(new DateTimeImmutable($expiresAt));
    }

    #[Test]
    public function it_accepts_an_expiry_of_exactly_365_days_after_issue(): void
    {
        $token = $this->token(new DateTimeImmutable('2027-11-15T12:00:00+00:00'));

        $this->assertEquals(new DateTimeImmutable('2027-11-15T12:00:00+00:00'), $token->expiresAt());
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
        $terms = new TokenTerms('Script', [TokenScope::Read, TokenScope::Write, TokenScope::Read], new DateTimeImmutable('2027-01-01T00:00:00+00:00'));

        $token = ApiToken::issue(Uuid::v7(), $terms, self::SECRET, new DateTimeImmutable(self::ISSUED_AT));

        $this->assertSame([TokenScope::Read, TokenScope::Write], $token->scopes());
    }

    /**
     * A token is only as strong as its secret; the app generates every secret, so anything else is a bug.
     *
     * @return iterable<string, array{string}>
     */
    public static function secretsNotMadeByTheGenerator(): iterable
    {
        yield 'one hex digit short' => ['bpt_' . \str_repeat('0', 63)];
        yield 'one hex digit too many' => ['bpt_' . \str_repeat('0', 65)];
        yield 'without the prefix' => [\str_repeat('0', 64)];
        yield 'with upper-case hex' => ['bpt_' . \str_repeat('A', 64)];
        yield 'with a trailing newline' => ['bpt_' . \str_repeat('0', 64) . "\n"];
        yield 'typed by hand' => ['bpt_mijn-eigen-token'];
    }

    #[Test]
    #[DataProvider('secretsNotMadeByTheGenerator')]
    public function it_refuses_a_secret_that_the_generator_did_not_make(#[SensitiveParameter] string $secret): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('An API token secret is bpt_ followed by 64 lower-case hex digits.'));

        ApiToken::issue(
            Uuid::v7(),
            new TokenTerms('Script', [TokenScope::Read], new DateTimeImmutable('2027-01-01T00:00:00+00:00')),
            $secret,
            new DateTimeImmutable(self::ISSUED_AT),
        );
    }

    #[Test]
    public function it_accepts_a_secret_of_exactly_64_hex_digits(): void
    {
        $token = ApiToken::issue(
            Uuid::v7(),
            new TokenTerms('Script', [TokenScope::Read], new DateTimeImmutable('2027-01-01T00:00:00+00:00')),
            'bpt_' . \str_repeat('f', 64),
            new DateTimeImmutable(self::ISSUED_AT),
        );

        $this->assertTrue($token->matches('bpt_' . \str_repeat('f', 64)));
    }

    private function token(DateTimeImmutable $expiresAt = new DateTimeImmutable('2027-11-15T12:00:00+00:00')): ApiToken
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

    #[Test]
    public function it_records_a_use_within_a_minute_of_the_last_one_only_once(): void
    {
        $token = $this->token();
        $token->markUsed(new DateTimeImmutable('2026-11-16T08:00:00+00:00'));

        $recorded = $token->markUsed(new DateTimeImmutable('2026-11-16T08:00:59+00:00'));

        $this->assertFalse($recorded);
        $this->assertEquals(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), $token->lastUsedAt());
    }

    #[Test]
    public function it_records_a_use_a_minute_after_the_last_one(): void
    {
        $token = $this->token();
        $token->markUsed(new DateTimeImmutable('2026-11-16T08:00:00+00:00'));

        $recorded = $token->markUsed(new DateTimeImmutable('2026-11-16T08:01:00+00:00'));

        $this->assertTrue($recorded);
        $this->assertEquals(new DateTimeImmutable('2026-11-16T08:01:00+00:00'), $token->lastUsedAt());
    }

    #[Test]
    public function it_admits_a_usable_token_of_an_active_member(): void
    {
        $token = $this->token();

        $this->assertNull($token->refusalAt(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), memberIsActive: true));
    }

    #[Test]
    public function it_refuses_once_revoked(): void
    {
        $token = $this->token();
        $token->revoke(new DateTimeImmutable('2026-11-16T07:00:00+00:00'));

        $this->assertSame(TokenRefusal::Revoked, $token->refusalAt(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), memberIsActive: true));
    }

    #[Test]
    public function it_refuses_from_its_expiry_on(): void
    {
        $token = $this->token(new DateTimeImmutable('2026-11-16T08:00:00+00:00'));

        $this->assertSame(TokenRefusal::Expired, $token->refusalAt(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), memberIsActive: true));
    }

    #[Test]
    public function it_refuses_for_a_member_who_is_deactivated_or_erased(): void
    {
        $token = $this->token();

        $this->assertSame(TokenRefusal::MemberInactive, $token->refusalAt(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), memberIsActive: false));
    }

    #[Test]
    public function it_names_revocation_before_an_inactive_member(): void
    {
        $token = $this->token();
        $token->revoke(new DateTimeImmutable('2026-11-16T07:00:00+00:00'));

        $this->assertSame(TokenRefusal::Revoked, $token->refusalAt(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), memberIsActive: false));
    }
}
