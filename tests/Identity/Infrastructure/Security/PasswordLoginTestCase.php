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
     * Every request of a test shares one kernel, so the session, the fakes and the login attempts carry over.
     */
    protected static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    protected static function logIn(KernelBrowser $client, string $email, #[SensitiveParameter] string $password, string $ip = '127.0.0.1'): void
    {
        $client->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $password], ['REMOTE_ADDR' => $ip]);
    }

    protected static function memberWithPassword(
        string $email = 'jan@example.org',
        string $name = 'Jan Bakker',
        string $displayName = 'Jan B.',
        ?string $hash = null,
    ): Member {
        $member = MemberFixture::stored(self::service(InMemoryMemberRepository::class), $email, $name, $displayName);
        $account = Account::open($member->id());
        $account->renewPasswordHash($hash ?? self::service(PasswordHasher::class)->hash(self::PASSWORD));
        self::service(InMemoryAccountRepository::class)->add($account);

        return $member;
    }

    protected static function account(Member $member): Account
    {
        $account = self::service(InMemoryAccountRepository::class)->ofMember($member->id());
        self::assertNotNull($account);

        return $account;
    }
}
