<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\PasswordHasher;
use App\Shared\Domain\AuditedAction;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\InMemoryKernel;
use App\Tests\Support\RecordingLogger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;

#[Group('functional')]
final class PasswordAuthenticatorTest extends PasswordLoginTestCase
{
    private const array LOGIN_FAILED = [
        'type' => 'urn:buurtpreventie:problem:login_failed',
        'title' => 'The e-mail address or password is not correct.',
        'status' => 401,
    ];

    #[Test]
    public function it_logs_the_member_in_with_a_session_on_the_web_channel_as_a_recent_login(): void
    {
        $client = self::client();
        $jan = self::memberWithPassword();

        self::logIn($client, 'jan@example.org', self::PASSWORD);
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/v1/_probe-identity');

        self::assertSame(['memberId' => $jan->id()->toRfc4122(), 'roles' => ['walker'], 'channel' => 'web', 'recent' => true], self::json($client));
    }

    #[Test]
    public function it_takes_the_address_in_any_case_and_with_spaces_around_it(): void
    {
        $client = self::client();
        self::memberWithPassword();

        self::logIn($client, ' Jan@Example.org ', self::PASSWORD);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_records_the_login_with_the_shortened_ip_address(): void
    {
        $client = self::client();
        $jan = self::memberWithPassword();

        self::logIn($client, 'jan@example.org', self::PASSWORD, ip: '192.0.2.57');

        self::assertEquals(
            [new AuditedAction('account.logged_in', 'member', $jan->id(), [
                'ip' => ['old' => null, 'new' => '192.0.2.0'],
                'method' => ['old' => null, 'new' => 'password'],
            ])],
            self::service(RecordingAuditTrail::class)->recorded,
        );
        self::assertEquals(new DateTimeImmutable(InMemoryKernel::NOW), self::account($jan)->lastLoginAt());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function attemptsThatFail(): iterable
    {
        yield 'wrong password' => ['jan@example.org', 'lantaarn-paal-43', 'wrong_password'];
        yield 'unknown address' => ['onbekend@example.org', self::PASSWORD, 'unknown_address'];
        yield 'no address at all' => ['geen adres', self::PASSWORD, 'unknown_address'];
        yield 'deactivated member' => ['piet@example.org', self::PASSWORD, 'member_inactive'];
        yield 'member without a password' => ['kees@example.org', self::PASSWORD, 'no_password'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function bodiesWithoutAnAddress(): iterable
    {
        yield 'empty address' => [['email' => '', 'password' => self::PASSWORD]];
        yield 'only spaces' => [['email' => '   ', 'password' => self::PASSWORD]];
        yield 'no address field' => [['password' => self::PASSWORD]];
        yield 'address not a text' => [['email' => 42, 'password' => self::PASSWORD]];
    }

    /**
     * Symfony refuses an empty user identifier; the form shows a field error instead of a failed login.
     *
     * @param array<string, mixed> $body
     */
    #[DataProvider('bodiesWithoutAnAddress')]
    #[Test]
    public function it_refuses_a_login_without_an_address_as_invalid_input_with_a_field_error(array $body): void
    {
        $client = self::client();
        self::memberWithPassword();

        $client->jsonRequest('POST', '/api/v1/auth/login', $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [
                'type' => 'urn:buurtpreventie:problem:invalid_input',
                'title' => 'Not all details are valid.',
                'status' => 422,
                'violations' => [['field' => 'email', 'message' => 'This value should not be blank.']],
            ],
            self::json($client),
        );
        self::assertSame([], self::service(RecordingAuditTrail::class)->recorded);
    }

    #[DataProvider('attemptsThatFail')]
    #[Test]
    public function it_refuses_every_failed_attempt_with_the_same_401_and_logs_why(string $email, #[SensitiveParameter] string $password, string $reason): void
    {
        $client = self::client();
        self::memberWithPassword();
        self::memberWithPassword('piet@example.org', 'Piet de Vries', 'Piet V.')->deactivate();
        MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'kees@example.org', 'Kees Jansen', 'Kees J.');

        self::logIn($client, $email, $password);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::LOGIN_FAILED, self::json($client));
        self::assertContains($reason, self::loggedReasons());
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_records_a_failed_attempt_on_a_known_member_without_an_actor_but_with_the_shortened_ip_address(): void
    {
        $client = self::client();
        $jan = self::memberWithPassword();

        self::logIn($client, 'jan@example.org', 'lantaarn-paal-43', ip: '2001:db8:85a3:8d3:1319:8a2e:370:7348');

        self::assertEquals(
            [new AuditedAction('account.login_failed', 'member', $jan->id(), [
                'ip' => ['old' => null, 'new' => '2001:db8:85a3::'],
                'method' => ['old' => null, 'new' => 'password'],
            ])],
            self::service(RecordingAuditTrail::class)->recorded,
        );
        self::assertNull(self::account($jan)->lastLoginAt());
    }

    #[Test]
    public function it_keeps_a_failed_attempt_on_an_unknown_address_out_of_the_audit_log_and_the_address_out_of_the_log(): void
    {
        $client = self::client();

        self::logIn($client, 'onbekend@example.org', self::PASSWORD);

        self::assertSame([], self::service(RecordingAuditTrail::class)->recorded);
        self::assertStringNotContainsString('onbekend', \json_encode(self::service(RecordingLogger::class)->records, \JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function it_refuses_the_sixth_failed_attempt_on_an_address_within_15_minutes_even_with_the_right_password(): void
    {
        $client = self::client();
        self::memberWithPassword();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::logIn($client, 'jan@example.org', 'lantaarn-paal-43');
            self::assertResponseStatusCodeSame(401, \sprintf('attempt %d', $attempt));
        }

        self::logIn($client, 'jan@example.org', self::PASSWORD);

        self::assertResponseStatusCodeSame(429);
        $retryAfter = (int) $client->getResponse()->headers->get('Retry-After');
        // Symfony rounds the wait up to whole minutes, so the 15-minute window can show as 16.
        self::assertTrue($retryAfter >= 60 && $retryAfter <= 960, \sprintf('Retry-After %d is not seconds within the window', $retryAfter));
        self::assertSame('urn:buurtpreventie:problem:too_many_requests', self::json($client)['type'] ?? null);
        self::assertContains('throttled', self::loggedReasons());
    }

    #[Test]
    public function it_still_takes_attempts_on_an_address_from_another_ip_address(): void
    {
        $client = self::client();
        self::memberWithPassword();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::logIn($client, 'jan@example.org', 'lantaarn-paal-43');
        }

        self::logIn($client, 'jan@example.org', self::PASSWORD, ip: '192.0.2.58');

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_clears_the_count_of_an_address_when_its_member_logs_in(): void
    {
        $client = self::client();
        self::memberWithPassword();
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            self::logIn($client, 'jan@example.org', 'lantaarn-paal-43');
        }
        self::logIn($client, 'jan@example.org', self::PASSWORD);

        self::logIn($client, 'jan@example.org', 'lantaarn-paal-43');
        self::logIn($client, 'jan@example.org', 'lantaarn-paal-43');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_refuses_the_31st_failed_attempt_from_one_ip_address_within_an_hour_whatever_the_address(): void
    {
        $client = self::client();
        self::memberWithPassword();
        for ($attempt = 1; $attempt <= 30; ++$attempt) {
            self::logIn($client, \sprintf('lid%d@example.org', $attempt), self::PASSWORD);
            self::assertResponseStatusCodeSame(401, \sprintf('attempt %d', $attempt));
        }

        self::logIn($client, 'jan@example.org', self::PASSWORD);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function it_renews_an_outdated_password_hash_at_login(): void
    {
        $client = self::client();
        $jan = self::memberWithPassword(hash: \password_hash(self::PASSWORD, \PASSWORD_BCRYPT, ['cost' => 5]));

        self::logIn($client, 'jan@example.org', self::PASSWORD);

        $hash = (string) self::account($jan)->passwordHash();
        $hasher = self::service(PasswordHasher::class);
        self::assertTrue($hasher->verify($hash, self::PASSWORD));
        self::assertFalse($hasher->needsRehash($hash));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function loggedReasons(): array
    {
        return \array_column(\array_column(self::service(RecordingLogger::class)->records, 'context'), 'reason');
    }
}
