<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Membership\Domain\Member;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Settings\Domain\Setting;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Result\CallToolResult;
use Nyholm\Psr7\Factory\Psr17Factory;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * A test of the MCP server through the client of the SDK, on the in-memory kernel: the same firewall, switch, scopes
 * and voters as a real client meets. One kernel serves every request of the test, so data arranged before the first
 * request stays. MCP is switched on unless a test says otherwise.
 */
abstract class McpTestCase extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret the tests present
    protected const string SECRET = 'bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    protected const string ENDPOINT = 'https://example.org/mcp';

    protected static function mcpBrowser(): KernelBrowser
    {
        $browser = self::createClient(server: ['HTTP_SEC_FETCH_SITE' => null]);
        $browser->disableReboot();
        self::service(InMemorySettingRepository::class)->add(Setting::of('integrations.mcp', '1'));

        return $browser;
    }

    /**
     * A new active member, the owner of the token of the test.
     */
    protected static function member(Role ...$roles): Member
    {
        return MemberFixture::stored(
            self::service(InMemoryMemberRepository::class),
            'jan@example.org',
            'Jan Bakker',
            'Jan B.',
            [] === $roles ? [Role::Walker] : \array_values($roles),
        );
    }

    /**
     * Another active member who consents to sharing contact details, so a test sees whether they leak.
     */
    protected static function walker(string $displayName, Role ...$roles): Member
    {
        $slug = \strtolower((string) \preg_replace('/\W+/', '', $displayName));
        $member = MemberFixture::stored(
            self::service(InMemoryMemberRepository::class),
            $slug . '@example.org',
            $displayName . ' Fullname',
            $displayName,
            [] === $roles ? [Role::Walker] : \array_values($roles),
        );
        $member->changeContactConsent(true);

        return $member;
    }

    /**
     * A round with these walkers, the first one its owner, minimum 2 and maximum 3.
     */
    protected static function round(string $startsAt, Member ...$walkers): Round
    {
        $first = \array_shift($walkers);
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            $first?->id() ?? Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        foreach ($walkers as $walker) {
            $round->signUp($walker->id(), new DateTimeImmutable('2026-11-01T12:00:00+00:00'));
        }
        self::service(InMemoryRoundRepository::class)->add($round);

        return $round;
    }

    /**
     * The JSON body of the same question over REST, with the token of the test.
     *
     * @param array<string, mixed> $query
     *
     * @return array<array-key, mixed>
     */
    protected static function rest(KernelBrowser $browser, string $path, array $query = []): array
    {
        $browser->request('GET', $path, $query, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);
        self::assertResponseIsSuccessful();

        return self::json($browser);
    }

    /**
     * The audit actions recorded so far, each with the channel it came in through.
     *
     * @return list<array{string, string}> action name and channel
     */
    protected static function audited(): array
    {
        $trail = self::service(RecordingAuditTrail::class);

        return \array_map(
            static fn(AuditedAction $action, Channel $channel): array => [$action->action, $channel->value],
            $trail->recorded,
            $trail->channels,
        );
    }

    /**
     * Sends a changing request over REST with the token of the test, for the parity of a writing tool.
     *
     * @param array<string, mixed>|null $body
     */
    protected static function restWrite(KernelBrowser $browser, string $method, string $path, ?array $body = null): void
    {
        $browser->jsonRequest($method, $path, $body ?? [], server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);
        self::assertResponseIsSuccessful();
    }

    protected static function token(Member $member, TokenScope ...$scopes): ApiToken
    {
        $token = ApiToken::issue(
            $member->id(),
            new TokenTerms(
                'Claude',
                [] === $scopes ? [TokenScope::Read, TokenScope::Write] : \array_values($scopes),
                new DateTimeImmutable('2027-11-01T00:00:00+00:00'),
            ),
            self::SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        );
        self::service(InMemoryApiTokenRepository::class)->add($token);

        return $token;
    }

    /**
     * A connected client of the SDK that speaks the given revision: 2026-07-28 without a session, an older one with
     * the handshake.
     */
    protected static function connect(
        KernelBrowser $browser,
        ProtocolVersion $version = ProtocolVersion::V2026_07_28,
        #[SensitiveParameter]
        string $secret = self::SECRET,
    ): Client {
        $builder = Client::builder()->setClientInfo('tests', '1.0')->setProtocolVersion($version)->setMaxRetries(0);
        if (ProtocolVersion::V2026_07_28 === $version) {
            $builder->setFallbackProtocolVersion(null);
        }

        $client = $builder->build();
        $factory = new Psr17Factory();
        $client->connect(new HttpTransport(
            self::ENDPOINT,
            ['Authorization' => 'Bearer ' . $secret],
            new KernelHttpClient($browser),
            $factory,
            $factory,
        ));

        return $client;
    }

    /**
     * The structured content of a successful call.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<array-key, mixed>
     */
    protected static function call(Client $client, string $tool, array $arguments = []): array
    {
        $result = $client->callTool($tool, $arguments);
        self::assertFalse($result->isError, self::text($result));
        self::assertIsArray($result->structuredContent);

        return $result->structuredContent;
    }

    /**
     * The problem of a failed call: type, title, status and the field errors or details.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<array-key, mixed>
     */
    protected static function failure(Client $client, string $tool, array $arguments = []): array
    {
        $result = $client->callTool($tool, $arguments);
        self::assertTrue($result->isError, self::text($result));
        self::assertIsArray($result->structuredContent);

        return $result->structuredContent;
    }

    protected static function text(CallToolResult $result): string
    {
        return \implode("\n", \array_map(
            static fn(mixed $content): string => $content instanceof TextContent && \is_string($content->text) ? $content->text : '',
            $result->content,
        ));
    }
}
