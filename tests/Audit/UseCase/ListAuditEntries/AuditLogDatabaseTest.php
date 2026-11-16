<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\ListAuditEntries;

use App\Identity\Domain\Account;
use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\PasswordHasher;
use App\Kernel;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\Channel;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use App\Tests\Database\TestDatabase;
use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;

/**
 * The whole path from a failed login to the log of the admin, through Doctrine: the log shows the shortened IP
 * address of the attempt and never the full one.
 */
#[Group('database')]
#[Group('functional')]
final class AuditLogDatabaseTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up password the member has
    private const string PASSWORD = 'lantaarn-paal-42';

    // @mago-expect lint:no-literal-password - a made-up wrong password the login refuses
    private const string WRONG_PASSWORD = 'fout-wachtwoord';

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
    }

    #[Test]
    public function it_shows_a_failed_login_with_a_shortened_ip_address_only(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $suffix = \bin2hex(\random_bytes(6));
        $jan = self::stored('jan' . $suffix, [Role::Walker], self::PASSWORD);
        $admin = self::stored('admin' . $suffix, [Role::Admin], null);
        self::service(EntityManagerInterface::class)->flush();

        $client->jsonRequest(
            'POST',
            '/api/v1/auth/login',
            ['email' => 'jan' . $suffix . '@example.org', 'password' => self::WRONG_PASSWORD],
            ['REMOTE_ADDR' => '192.0.2.57'],
        );
        self::assertResponseStatusCodeSame(401);
        $client->loginUser(new MemberUser($admin->id(), $admin->roles()), 'browser', [TokenExecutionContext::CHANNEL => Channel::Web]);
        $client->request('GET', '/api/v1/admin/audit', ['member' => $jan->id()->toRfc4122(), 'action' => 'account.login_failed']);

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('192.0.2.0', $body);
        self::assertStringNotContainsString('192.0.2.57', $body);
    }

    /**
     * @param list<Role> $roles
     */
    private static function stored(string $local, array $roles, #[SensitiveParameter] ?string $password): Member
    {
        $member = Member::register(new MemberDetails(EmailAddress::fromString($local . '@example.org'), 'Lid ' . $local, 'Lid ' . $local));
        $member->changeRoles($roles);
        self::service(MemberRepository::class)->add($member);
        if (null !== $password) {
            $account = Account::open($member->id());
            $account->renewPasswordHash(self::service(PasswordHasher::class)->hash($password));
            self::service(AccountRepository::class)->add($account);
        }

        return $member;
    }
}
