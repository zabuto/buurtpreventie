<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\CreateApiToken;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Identity\Infrastructure\Security\PasswordLoginTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * Creating, listing and revoking one's own API tokens, which only a browser session does.
 */
#[Group('functional')]
final class ApiTokenEndpointsTest extends PasswordLoginTestCase
{
    // @mago-expect lint:no-literal-password - the secret FixedSecretGenerator hands out
    private const string NEW_SECRET = 'bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    // @mago-expect lint:no-literal-password - a made-up secret of a token the test stores
    private const string OTHER_SECRET = 'bpt_fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

    private const array NEW_TOKEN = ['name' => 'Agenda', 'scopes' => ['read'], 'expiresAt' => '2027-02-14T10:00:00+01:00'];

    #[Test]
    public function it_creates_a_token_in_a_recent_session_and_shows_its_secret_once(): void
    {
        $client = self::loggedIn();

        $client->jsonRequest('POST', '/api/v1/me/tokens', self::NEW_TOKEN);

        self::assertResponseStatusCodeSame(201);
        $answer = self::json($client);
        self::assertSame(self::NEW_SECRET, $answer['token'] ?? null);
        self::assertIsString($answer['id'] ?? null);
    }

    #[Test]
    public function it_lets_the_new_token_in(): void
    {
        $client = self::loggedIn();
        $client->jsonRequest('POST', '/api/v1/me/tokens', self::NEW_TOKEN);

        $client->request('GET', '/api/v1/_probe-identity', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::NEW_SECRET]);

        self::assertSame('api', self::json($client)['channel'] ?? null);
    }

    #[Test]
    public function it_never_shows_the_secret_in_the_list(): void
    {
        $client = self::loggedIn();
        $client->jsonRequest('POST', '/api/v1/me/tokens', self::NEW_TOKEN);

        $client->request('GET', '/api/v1/me/tokens');

        self::assertResponseIsSuccessful();
        self::assertSame(['Agenda'], self::listedNames($client));
        self::assertStringNotContainsString(\substr(self::NEW_SECRET, 4), (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function it_refuses_a_new_token_in_a_session_that_is_not_recent(): void
    {
        $client = self::createClient();
        self::actingAs($client);

        $client->jsonRequest('POST', '/api/v1/me/tokens', self::NEW_TOKEN);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:reauthentication_required', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_refuses_an_expiry_more_than_365_days_ahead(): void
    {
        $client = self::loggedIn();

        $client->jsonRequest('POST', '/api/v1/me/tokens', [...self::NEW_TOKEN, 'expiresAt' => '2027-11-16T11:00:01+01:00']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_token_expiry', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_refuses_a_token_without_an_expiry(): void
    {
        $client = self::loggedIn();

        $client->jsonRequest('POST', '/api/v1/me/tokens', ['name' => 'Agenda', 'scopes' => ['read']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_input', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_revokes_an_own_token_which_then_lets_nobody_in(): void
    {
        $client = self::loggedIn();
        $client->jsonRequest('POST', '/api/v1/me/tokens', self::NEW_TOKEN);
        $id = self::json($client)['id'] ?? null;
        self::assertIsString($id);

        $client->request('DELETE', '/api/v1/me/tokens/' . $id);

        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/v1/_probe-identity', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::NEW_SECRET]);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_refuses_to_revoke_a_token_of_another_member(): void
    {
        $client = self::loggedIn();
        $piet = self::memberWithPassword('piet@example.org', 'Piet de Vries', 'Piet V.');
        $token = self::storedToken($piet->id()->toRfc4122(), self::OTHER_SECRET, [TokenScope::Read]);

        $client->request('DELETE', '/api/v1/me/tokens/' . $token->id()->toRfc4122());

        self::assertResponseStatusCodeSame(403);
        self::assertNull($token->revokedAt());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenManagementRequests(): iterable
    {
        yield 'listing' => ['GET', '/api/v1/me/tokens'];
        yield 'creating' => ['POST', '/api/v1/me/tokens'];
        yield 'revoking' => ['DELETE', '/api/v1/me/tokens/0193a3c0-0000-7000-8000-000000000001'];
    }

    /**
     * A leaked token could otherwise make a token of its own, or revoke the token its owner needs.
     */
    #[DataProvider('tokenManagementRequests')]
    #[Test]
    public function it_refuses_every_request_with_an_api_token(string $method, string $path): void
    {
        $client = self::client();
        $jan = self::memberWithPassword();
        self::storedToken($jan->id()->toRfc4122(), self::OTHER_SECRET, [TokenScope::Read, TokenScope::Write]);

        $client->jsonRequest($method, $path, self::NEW_TOKEN, ['HTTP_AUTHORIZATION' => 'Bearer ' . self::OTHER_SECRET]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:browser_only', self::json($client)['type'] ?? null);
    }

    private static function loggedIn(): KernelBrowser
    {
        $client = self::client();
        self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);
        self::assertResponseStatusCodeSame(204);

        return $client;
    }

    /**
     * @param list<TokenScope> $scopes
     */
    private static function storedToken(string $memberId, #[SensitiveParameter] string $secret, array $scopes): ApiToken
    {
        $token = ApiToken::issue(
            Uuid::fromString($memberId),
            new TokenTerms('Script', $scopes, new DateTimeImmutable('2027-01-01T00:00:00+00:00')),
            $secret,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        );
        self::service(InMemoryApiTokenRepository::class)->add($token);

        return $token;
    }

    /**
     * @return list<string>
     */
    private static function listedNames(KernelBrowser $client): array
    {
        /** @var array{tokens: list<array{name: string}>} $list */
        $list = self::json($client);

        return \array_column($list['tokens'], 'name');
    }
}
