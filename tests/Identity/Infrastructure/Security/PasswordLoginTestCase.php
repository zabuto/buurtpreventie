<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\Account;
use App\Identity\Domain\PasswordHasher;
use App\Membership\Domain\Member;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Support\ApiTestCase;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * An endpoint test with a member with a password in the fakes of the running kernel, and a client that logs in with
 * it.
 */
abstract class PasswordLoginTestCase extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up password the tests log in with
    protected const string PASSWORD = 'lantaarn-paal-42';

    /**
     * Every request of a test shares one kernel, so the session, the cookies, the fakes and the login attempts carry
     * over.
     *
     * @param array<array-key, mixed> $server such as HTTPS, for a test that needs the Secure cookie sent back
     */
    protected static function client(array $server = []): KernelBrowser
    {
        $client = self::createClient(server: $server);
        $client->disableReboot();

        return $client;
    }

    /**
     * @param bool|null $rememberMe null leaves the field out of the body
     */
    protected static function logIn(
        KernelBrowser $client,
        string $email,
        #[SensitiveParameter]
        string $password,
        string $ip = '127.0.0.1',
        ?bool $rememberMe = null,
    ): void {
        $body = ['email' => $email, 'password' => $password];
        if (null !== $rememberMe) {
            $body['rememberMe'] = $rememberMe;
        }

        $client->jsonRequest('POST', '/api/v1/auth/login', $body, ['REMOTE_ADDR' => $ip]);
    }

    protected static function memberWithPassword(
        string $email = 'jan@example.org',
        string $name = 'Jan Bakker',
        string $displayName = 'Jan B.',
        ?string $hash = null,
    ): Member {
        $member = MemberFixture::stored(self::service(InMemoryMemberRepository::class), $email, $name, $displayName);
        self::withPassword($member, $hash);

        return $member;
    }

    /**
     * Gives a member who exists, such as the one actingAs() logs in, an account with PASSWORD.
     */
    protected static function withPassword(Member $member, ?string $hash = null): void
    {
        $account = Account::open($member->id());
        $account->renewPasswordHash($hash ?? self::service(PasswordHasher::class)->hash(self::PASSWORD));
        self::service(InMemoryAccountRepository::class)->add($account);
    }

    protected static function account(Member $member): Account
    {
        $account = self::service(InMemoryAccountRepository::class)->ofMember($member->id());
        self::assertNotNull($account);

        return $account;
    }
}
