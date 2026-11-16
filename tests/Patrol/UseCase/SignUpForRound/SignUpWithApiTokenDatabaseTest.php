<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\SignUpForRound;

use App\Identity\Domain\Account;
use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\PasswordHasher;
use App\Kernel;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberRepository;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundRepository;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Role;
use App\Tests\Database\TestDatabase;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\InMemoryKernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * The curl scenario of docs/getting-started.md along the whole path: a member logs in, makes a token, and a request
 * with that token alone, as curl sends it, signs the member up for a round. A token that may only read is refused.
 */
#[Group('database')]
#[Group('functional')]
final class SignUpWithApiTokenDatabaseTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up password the member has
    private const string PASSWORD = 'lantaarn-paal-42';

    /**
     * Null when the test skipped before it stopped the clock: tearDown() runs after a skip as well.
     */
    private ?ClockInterface $previousClock = null;

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $this->previousClock = Clock::get();
        Clock::set(new MockClock(InMemoryKernel::NOW));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null !== $this->previousClock) {
            Clock::set($this->previousClock);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_signs_the_member_up_with_a_token_that_may_write(): void
    {
        [$client, $member, $round] = self::arranged();
        $token = self::newToken($client, ['read', 'write']);
        $client->getCookieJar()->clear();

        $client->request('PUT', self::path($round), server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseStatusCodeSame(204);
        self::service(EntityManagerInterface::class)->clear();
        self::assertTrue(self::service(RoundRepository::class)->get($round->id())->isSignedUp($member->id()));
    }

    #[Test]
    public function it_refuses_a_token_that_may_only_read(): void
    {
        [$client, $member, $round] = self::arranged();
        $token = self::newToken($client, ['read']);
        $client->getCookieJar()->clear();

        $client->request('PUT', self::path($round), server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:insufficient_scope', self::json($client)['type'] ?? null);
        self::service(EntityManagerInterface::class)->clear();
        self::assertFalse(self::service(RoundRepository::class)->get($round->id())->isSignedUp($member->id()));
    }

    /**
     * A walker who logged in with the password a moment ago, so the session may make a token, and a round of
     * another walker ahead.
     *
     * @return array{KernelBrowser, Member, Round}
     */
    private static function arranged(): array
    {
        $client = self::createClient();
        $client->disableReboot();
        $suffix = \bin2hex(\random_bytes(6));
        $member = self::walker('jan' . $suffix, self::PASSWORD);
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('2026-11-20T21:00:00+00:00')),
            self::walker('piet' . $suffix, null)->id(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable('2026-11-10T09:00:00+00:00'),
        );
        self::service(RoundRepository::class)->add($round);
        self::service(EntityManagerInterface::class)->flush();

        $client->jsonRequest('POST', '/api/v1/auth/login', ['email' => 'jan' . $suffix . '@example.org', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        return [$client, $member, $round];
    }

    /**
     * @param list<string> $scopes
     */
    private static function newToken(KernelBrowser $client, array $scopes): string
    {
        $client->jsonRequest('POST', '/api/v1/me/tokens', ['name' => 'curl', 'scopes' => $scopes, 'expiresAt' => '2027-02-14T10:00:00+01:00']);
        self::assertResponseStatusCodeSame(201);
        $token = self::json($client)['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private static function walker(string $local, #[SensitiveParameter] ?string $password): Member
    {
        $member = Member::register(new MemberDetails(EmailAddress::fromString($local . '@example.org'), 'Lid ' . $local, 'Lid ' . $local));
        $member->changeRoles([Role::Walker]);
        self::service(MemberRepository::class)->add($member);
        if (null !== $password) {
            $account = Account::open($member->id());
            $account->renewPasswordHash(self::service(PasswordHasher::class)->hash($password));
            self::service(AccountRepository::class)->add($account);
        }

        return $member;
    }

    private static function path(Round $round): string
    {
        return \sprintf('/api/v1/rounds/%s/walkers/me', $round->id()->toRfc4122());
    }
}
