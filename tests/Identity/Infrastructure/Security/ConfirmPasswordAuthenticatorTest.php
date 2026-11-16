<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Shared\Domain\AuditedAction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * A session that is no longer recent, such as one kept by "stay logged in", proves the member again with the password
 * before a sensitive action.
 */
#[Group('functional')]
final class ConfirmPasswordAuthenticatorTest extends PasswordLoginTestCase
{
    #[Test]
    public function it_makes_the_session_of_the_member_recent_with_the_right_password(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        self::withPassword($member);
        self::assertFalse(self::probe($client)['recent'] ?? null);

        self::confirm($client, self::PASSWORD);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['memberId' => $member->id()->toRfc4122(), 'roles' => ['walker'], 'channel' => 'web', 'recent' => true], self::probe($client));
        self::assertSame([], self::service(RecordingAuditTrail::class)->recorded);
    }

    #[Test]
    public function it_refuses_a_wrong_password_records_the_failure_and_keeps_the_session_as_it_was(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        self::withPassword($member);

        self::confirm($client, 'lantaarn-paal-43', ip: '192.0.2.57');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('urn:buurtpreventie:problem:login_failed', self::json($client)['type'] ?? null);
        self::assertEquals(
            [new AuditedAction('account.login_failed', 'member', $member->id(), [
                'ip' => ['old' => null, 'new' => '192.0.2.0'],
                'method' => ['old' => null, 'new' => 'password'],
            ])],
            self::service(RecordingAuditTrail::class)->recorded,
        );
        self::assertSame([$member->id()->toRfc4122(), false], [self::probe($client)['memberId'] ?? null, self::probe($client)['recent'] ?? null]);
    }

    /**
     * A member who only logs in with codes has no password; logging in with a new code makes the session recent.
     */
    #[Test]
    public function it_refuses_a_member_without_a_password(): void
    {
        $client = self::client();
        self::actingAs($client);

        self::confirm($client, self::PASSWORD);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('urn:buurtpreventie:problem:login_failed', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_refuses_a_confirmation_without_a_session(): void
    {
        $client = self::client();

        self::confirm($client, self::PASSWORD);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('urn:buurtpreventie:problem:unauthorized', self::json($client)['type'] ?? null);
    }

    private static function confirm(KernelBrowser $client, #[SensitiveParameter] string $password, string $ip = '127.0.0.1'): void
    {
        $client->jsonRequest('POST', '/api/v1/auth/confirm', ['password' => $password], ['REMOTE_ADDR' => $ip]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function probe(KernelBrowser $client): array
    {
        $client->request('GET', '/api/v1/_probe-identity');

        return self::json($client);
    }
}
