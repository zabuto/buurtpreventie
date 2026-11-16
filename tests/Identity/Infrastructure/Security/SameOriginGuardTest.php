<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The browser firewall takes a changing request only when it comes from the frontend on the same origin: by
 * Sec-Fetch-Site, by Origin where a browser sends no Sec-Fetch-Site, or by the token header the frontend sets where a
 * browser sends neither.
 */
#[Group('functional')]
final class SameOriginGuardTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up token as the frontend would generate it
    private const string FRONTEND_TOKEN = 'b3f1c2d4e5a6978812345678';

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function requestsFromTheFrontend(): iterable
    {
        yield 'same origin by Sec-Fetch-Site' => [['HTTP_SEC_FETCH_SITE' => 'same-origin']];
        yield 'same origin by Origin' => [['HTTP_SEC_FETCH_SITE' => null, 'HTTP_ORIGIN' => 'http://localhost']];
        yield 'token header without origin information' => [['HTTP_SEC_FETCH_SITE' => null, 'HTTP_CSRF_TOKEN' => self::FRONTEND_TOKEN]];
    }

    /**
     * @param array<string, string|null> $server
     */
    #[DataProvider('requestsFromTheFrontend')]
    #[Test]
    public function it_takes_a_change_from_the_frontend(array $server): void
    {
        $client = self::createClient(server: $server);
        self::actingAs($client);

        self::change($client);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function requestsFromElsewhere(): iterable
    {
        yield 'another site by Sec-Fetch-Site' => [['HTTP_SEC_FETCH_SITE' => 'cross-site']];
        yield 'another site by Origin' => [['HTTP_SEC_FETCH_SITE' => null, 'HTTP_ORIGIN' => 'https://elders.example']];
        yield 'no origin information and no token header' => [['HTTP_SEC_FETCH_SITE' => null]];
        yield 'a token header that is too short' => [['HTTP_SEC_FETCH_SITE' => null, 'HTTP_CSRF_TOKEN' => \substr(self::FRONTEND_TOKEN, 0, 23)]];
    }

    /**
     * @param array<string, string|null> $server
     */
    #[DataProvider('requestsFromElsewhere')]
    #[Test]
    public function it_refuses_a_change_from_elsewhere_with_403(array $server): void
    {
        $client = self::createClient(server: $server);
        self::actingAs($client);

        self::change($client);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(
            [
                'type' => 'urn:buurtpreventie:problem:csrf_check_failed',
                'title' => 'The app could not check that this request comes from this site. Reload the page and try again.',
                'status' => 403,
            ],
            self::json($client),
        );
    }

    #[Test]
    public function it_refuses_a_login_from_another_site_before_checking_the_password(): void
    {
        $client = self::createClient(server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);

        // @mago-expect lint:no-literal-password - a made-up password the login never checks
        $client->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'jan@example.org', 'password' => 'lantaarn-paal-42']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:csrf_check_failed', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_lets_a_read_from_another_site_through(): void
    {
        $client = self::createClient(server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::actingAs($client);

        $client->request('GET', '/api/v1/_probe-identity');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function it_leaves_a_request_with_an_api_token_to_the_token_firewall(): void
    {
        $client = self::createClient(server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $secret = 'bpt_' . \str_repeat('a', 64);
        self::service(InMemoryApiTokenRepository::class)->add(ApiToken::issue(
            self::actingAs($client)->id(),
            new TokenTerms('Script', [TokenScope::Write]),
            $secret,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        ));

        $client->request('POST', '/api/v1/_probe-identity', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $secret]);

        self::assertSame('api', self::json($client)['channel'] ?? null);
    }

    private static function change(KernelBrowser $client): void
    {
        $client->jsonRequest('POST', '/api/v1/_probe', ['name' => 'Jan']);
    }
}
