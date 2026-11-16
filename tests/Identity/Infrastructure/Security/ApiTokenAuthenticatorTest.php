<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Membership\Domain\Member;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\InMemoryKernel;
use App\Tests\Support\RecordingLogger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class ApiTokenAuthenticatorTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret the test presents
    private const string SECRET = 'bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    #[Test]
    public function it_runs_the_request_as_the_member_of_the_token_through_the_api_channel_never_as_a_recent_login(): void
    {
        $client = self::createClient();
        $jan = self::member();
        self::token($jan);

        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertResponseIsSuccessful();
        self::assertSame(['memberId' => $jan->id()->toRfc4122(), 'roles' => ['walker'], 'channel' => 'api', 'recent' => false], self::json($client));
    }

    #[Test]
    public function it_records_that_the_token_was_used(): void
    {
        $client = self::createClient();
        $token = self::token(self::member());

        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertEquals(new DateTimeImmutable(InMemoryKernel::NOW), $token->lastUsedAt());
    }

    #[Test]
    public function it_starts_no_session_for_a_token(): void
    {
        $client = self::createClient();
        self::token(self::member());

        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertResponseNotHasCookie('MOCKSESSID');
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function headersThatHoldNoUsableToken(): iterable
    {
        yield 'bearer without a secret' => ['Bearer', 'malformed'];
        yield 'secret in another format' => ['Bearer bpt_123', 'malformed'];
        yield 'another scheme' => ['Basic amFuOmdlaGVpbQ==', 'malformed'];
        yield 'unknown secret' => ['Bearer bpt_' . \str_repeat('e', 64), 'unknown'];
    }

    #[DataProvider('headersThatHoldNoUsableToken')]
    #[Test]
    public function it_refuses_a_header_without_a_usable_token_with_an_empty_401_and_logs_why(string $header, string $reason): void
    {
        $client = self::createClient();
        self::token(self::member());

        $client->request('GET', '/api/v1/_probe-identity', server: ['HTTP_AUTHORIZATION' => $header]);

        self::assertRefusedFor($client, $reason);
    }

    #[Test]
    public function it_refuses_a_revoked_token(): void
    {
        $client = self::createClient();
        $token = self::token(self::member());
        $token->revoke(new DateTimeImmutable('2026-11-15T10:00:00+00:00'));

        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertRefusedFor($client, 'revoked');
    }

    #[Test]
    public function it_refuses_an_expired_token(): void
    {
        $client = self::createClient();
        self::token(self::member(), new DateTimeImmutable(InMemoryKernel::NOW));

        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertRefusedFor($client, 'expired');
    }

    #[Test]
    public function it_refuses_the_token_of_a_deactivated_member(): void
    {
        $client = self::createClient();
        $jan = self::member();
        self::token($jan);
        $jan->deactivate();

        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertRefusedFor($client, 'member_inactive');
    }

    #[Test]
    public function it_refuses_the_token_of_an_erased_member(): void
    {
        $client = self::createClient();
        $jan = self::member();
        self::token($jan);
        $jan->erase(new DateTimeImmutable('2026-11-15T10:00:00+00:00'));

        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertRefusedFor($client, 'member_inactive');
    }

    #[Test]
    public function it_refuses_a_token_from_the_first_request_after_it_was_revoked(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $token = self::token(self::member());
        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));
        self::assertResponseIsSuccessful();

        $token->revoke(new DateTimeImmutable(InMemoryKernel::NOW));
        $client->request('GET', '/api/v1/_probe-identity', server: self::bearer(self::SECRET));

        self::assertResponseStatusCodeSame(401);
    }

    private static function member(): Member
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    private static function token(Member $member, ?DateTimeImmutable $expiresAt = null): ApiToken
    {
        $token = ApiToken::issue(
            $member->id(),
            new TokenTerms('Script', [TokenScope::Read, TokenScope::Write], $expiresAt),
            self::SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        );
        self::service(InMemoryApiTokenRepository::class)->add($token);

        return $token;
    }

    /**
     * @return array<string, string>
     */
    private static function bearer(#[SensitiveParameter] string $secret): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $secret];
    }

    private static function assertRefusedFor(KernelBrowser $client, string $reason): void
    {
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame('', $client->getResponse()->getContent());

        $reasons = \array_column(\array_column(self::service(RecordingLogger::class)->records, 'context'), 'reason');
        self::assertContains($reason, $reasons);
    }
}
