<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Mcp;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Settings\Domain\Setting;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class McpSwitchTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret the test presents
    private const string SECRET = 'bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /**
     * @return iterable<string, array{string}>
     */
    public static function methods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'POST' => ['POST'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('methods')]
    #[Test]
    public function it_answers_404_on_every_request_to_mcp_while_mcp_is_off_without_looking_at_the_token(string $method): void
    {
        $client = self::createClient();
        $token = self::token();

        $client->request($method, '/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET, 'HTTP_HOST' => 'example.org']);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('urn:buurtpreventie:problem:not_found', self::json($client)['type']);
        self::assertNull($token->lastUsedAt());
    }

    #[Test]
    public function it_answers_404_without_a_token_too_while_mcp_is_off(): void
    {
        $client = self::createClient();

        $client->request('POST', '/mcp', server: ['HTTP_HOST' => 'example.org']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function it_lets_requests_to_mcp_through_once_mcp_is_on(): void
    {
        $client = self::createClient();
        self::service(InMemorySettingRepository::class)->add(Setting::of('integrations.mcp', '1'));

        $client->request('POST', '/mcp', server: ['HTTP_HOST' => 'example.org']);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_closes_mcp_from_the_first_request_after_it_was_switched_off(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $settings = self::service(InMemorySettingRepository::class);
        $settings->add(Setting::of('integrations.mcp', '1'));
        $client->request('POST', '/mcp', server: ['HTTP_HOST' => 'example.org']);
        self::assertResponseStatusCodeSame(401);

        $settings->add(Setting::of('integrations.mcp', '0'));
        $client->request('POST', '/mcp', server: ['HTTP_HOST' => 'example.org']);

        self::assertResponseStatusCodeSame(404);
    }

    private static function token(): ApiToken
    {
        $member = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $token = ApiToken::issue(
            $member->id(),
            new TokenTerms('Claude', [TokenScope::Read], new DateTimeImmutable('2027-11-01T00:00:00+00:00')),
            self::SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        );
        self::service(InMemoryApiTokenRepository::class)->add($token);

        return $token;
    }
}
