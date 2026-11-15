<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A token only reaches what its scopes cover: read for reading, write for every change, admin for /api/v1/admin/.
 * The role of the member still decides in the voters on top of this.
 */
#[Group('functional')]
final class TokenScopeTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret the test presents
    private const string SECRET = 'bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /**
     * @return iterable<string, array{list<TokenScope>, string, string, int}>
     */
    public static function scopesAgainstRequests(): iterable
    {
        yield 'read reads' => [[TokenScope::Read], 'GET', '/api/v1/_probe-identity', 200];
        yield 'read does not write' => [[TokenScope::Read], 'POST', '/api/v1/_probe-identity', 403];
        yield 'write writes' => [[TokenScope::Write], 'POST', '/api/v1/_probe-identity', 200];
        yield 'write does not read' => [[TokenScope::Write], 'GET', '/api/v1/_probe-identity', 403];
        yield 'read and write do not reach admin' => [[TokenScope::Read, TokenScope::Write], 'GET', '/api/v1/admin/_probe-identity', 403];
        yield 'admin reaches admin reads' => [[TokenScope::Admin], 'GET', '/api/v1/admin/_probe-identity', 200];
        yield 'admin reaches admin writes' => [[TokenScope::Admin], 'POST', '/api/v1/admin/_probe-identity', 200];
        yield 'admin alone does not read elsewhere' => [[TokenScope::Admin], 'GET', '/api/v1/_probe-identity', 403];
    }

    /**
     * @param list<TokenScope> $scopes
     */
    #[DataProvider('scopesAgainstRequests')]
    #[Test]
    public function it_lets_a_token_reach_only_what_its_scopes_cover(array $scopes, string $method, string $path, int $status): void
    {
        $client = self::createClient();
        $jan = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
        self::service(InMemoryApiTokenRepository::class)->add(ApiToken::issue(
            $jan->id(),
            new TokenTerms('Script', $scopes),
            self::SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        ));

        $client->request($method, $path, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        self::assertResponseStatusCodeSame($status);
    }

    #[Test]
    public function it_answers_a_missing_scope_as_a_problem(): void
    {
        $client = self::createClient();
        $jan = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
        self::service(InMemoryApiTokenRepository::class)->add(ApiToken::issue(
            $jan->id(),
            new TokenTerms('Script', [TokenScope::Read]),
            self::SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        ));

        $client->request('POST', '/api/v1/_probe-identity', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', self::json($client)['type'] ?? null);
    }
}
