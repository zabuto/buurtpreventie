<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\LoginChallenge;
use App\Identity\Domain\LoginCodeHasher;
use App\Membership\Domain\Member;
use App\Shared\Domain\AuditedAction;
use App\Tests\Identity\Fake\InMemoryLoginChallengeRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\MockClock;

#[Group('functional')]
final class LoginCodeAuthenticatorTest extends ApiTestCase
{
    private const string CHALLENGE = '0123456789abcdef0123456789abcdef';
    private const string OTHER_CHALLENGE = 'fedcba9876543210fedcba9876543210';

    private const array CODE_ENDED = [
        'type' => 'urn:buurtpreventie:problem:login_code_ended',
        'title' => 'This code no longer works. Ask for a new code.',
        'status' => 401,
    ];

    #[Test]
    public function it_logs_the_member_in_with_the_code_from_the_mail_on_the_web_channel_as_a_recent_login(): void
    {
        $client = self::client();
        $jan = self::member();
        self::challenge($jan, self::CHALLENGE, '482913');

        self::verify($client, self::CHALLENGE, '482913');

        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertSame(['memberId' => $jan->id()->toRfc4122(), 'roles' => ['walker'], 'channel' => 'web', 'recent' => true], self::json($client));
    }

    #[Test]
    public function it_records_the_login_with_the_code_as_its_method_and_the_shortened_ip_address(): void
    {
        $client = self::client();
        $jan = self::member();
        self::challenge($jan, self::CHALLENGE, '482913');

        self::verify($client, self::CHALLENGE, '482913', ip: '192.0.2.57');

        self::assertEquals(
            [new AuditedAction('account.logged_in', 'member', $jan->id(), [
                'ip' => ['old' => null, 'new' => '192.0.2.0'],
                'method' => ['old' => null, 'new' => 'code'],
            ])],
            self::service(RecordingAuditTrail::class)->recorded,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function codesAsTyped(): iterable
    {
        yield 'in two groups' => ['482 913'];
        yield 'with a hyphen' => ['482-913'];
        yield 'with spaces around it' => [' 482913 '];
    }

    #[DataProvider('codesAsTyped')]
    #[Test]
    public function it_takes_the_code_with_the_spaces_and_hyphens_a_member_types_or_pastes(string $typed): void
    {
        $client = self::client();
        self::challenge(self::member(), self::CHALLENGE, '482913');

        self::verify($client, self::CHALLENGE, $typed);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_refuses_a_wrong_code_with_the_attempts_left_and_records_the_failure(): void
    {
        $client = self::client();
        $jan = self::member();
        self::challenge($jan, self::CHALLENGE, '482913');

        self::verify($client, self::CHALLENGE, '482914', ip: '192.0.2.57');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            [
                'type' => 'urn:buurtpreventie:problem:login_code_wrong',
                'title' => 'This code is not correct.',
                'status' => 401,
                'attemptsLeft' => 4,
            ],
            self::json($client),
        );
        self::assertEquals(
            [new AuditedAction('account.login_failed', 'member', $jan->id(), [
                'ip' => ['old' => null, 'new' => '192.0.2.0'],
                'method' => ['old' => null, 'new' => 'code'],
            ])],
            self::service(RecordingAuditTrail::class)->recorded,
        );
        self::assertContains('wrong_code', self::loggedReasons());
    }

    #[Test]
    public function it_takes_a_code_only_with_the_challenge_it_was_sent_for(): void
    {
        $client = self::client();
        $jan = self::member();
        self::challenge($jan, self::CHALLENGE, '482913');
        self::challenge(self::member('piet@example.org', 'Piet de Vries', 'Piet V.'), self::OTHER_CHALLENGE, '105729');

        self::verify($client, self::OTHER_CHALLENGE, '482913');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('urn:buurtpreventie:problem:login_code_wrong', self::json($client)['type'] ?? null);
    }

    /**
     * The right code comes from another IP address, past the login throttling: the challenge itself takes no more.
     */
    #[Test]
    public function it_refuses_even_the_right_code_after_five_wrong_ones(): void
    {
        $client = self::client();
        self::challenge(self::member(), self::CHALLENGE, '482913');
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            self::verify($client, self::CHALLENGE, '000000');
        }
        self::assertSame(1, self::json($client)['attemptsLeft'] ?? null);
        self::verify($client, self::CHALLENGE, '000000');

        self::verify($client, self::CHALLENGE, '482913', ip: '192.0.2.58');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::CODE_ENDED, self::json($client));
    }

    #[Test]
    public function it_takes_a_code_once(): void
    {
        $client = self::client();
        self::challenge(self::member(), self::CHALLENGE, '482913');
        self::verify($client, self::CHALLENGE, '482913');

        self::verify($client, self::CHALLENGE, '482913');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::CODE_ENDED, self::json($client));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function momentsAfterTheRequest(): iterable
    {
        yield 'a second before the hour is up' => ['+59 minutes 59 seconds', 204];
        yield 'when the hour is up' => ['+60 minutes', 401];
    }

    #[DataProvider('momentsAfterTheRequest')]
    #[Test]
    public function it_takes_a_code_for_60_minutes(string $later, int $status): void
    {
        $client = self::client();
        self::challenge(self::member(), self::CHALLENGE, '482913');

        self::service(MockClock::class)->modify($later);
        self::verify($client, self::CHALLENGE, '482913');

        self::assertResponseStatusCodeSame($status);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownChallenges(): iterable
    {
        yield 'unknown id' => [self::OTHER_CHALLENGE];
        yield 'id in another format' => ['../' . self::CHALLENGE];
    }

    #[DataProvider('unknownChallenges')]
    #[Test]
    public function it_refuses_an_unknown_challenge_like_one_that_ended(string $challengeId): void
    {
        $client = self::client();
        self::challenge(self::member(), self::CHALLENGE, '482913');

        self::verify($client, $challengeId, '482913');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::CODE_ENDED, self::json($client));
    }

    /**
     * A challenge for an address without a member takes no code, so typing one fails like a wrong code and shows
     * nothing about the address.
     */
    #[Test]
    public function it_refuses_every_code_for_an_address_without_a_member_like_a_wrong_code(): void
    {
        $client = self::client();
        self::challenge(null, self::CHALLENGE, '482913');

        self::verify($client, self::CHALLENGE, '482913');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('urn:buurtpreventie:problem:login_code_wrong', self::json($client)['type'] ?? null);
        self::assertSame([], self::service(RecordingAuditTrail::class)->recorded);
    }

    #[Test]
    public function it_refuses_the_code_of_a_member_who_was_deactivated_since_it_was_sent(): void
    {
        $client = self::client();
        $jan = self::member();
        self::challenge($jan, self::CHALLENGE, '482913');

        $jan->deactivate();
        self::verify($client, self::CHALLENGE, '482913');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::CODE_ENDED, self::json($client));
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_has_no_verification_by_get(): void
    {
        $client = self::client();

        $client->request('GET', '/api/v1/auth/login-code/verify');

        self::assertResponseStatusCodeSame(405);
    }

    /**
     * Every request of a test shares one kernel, so the session, the fakes and the clock carry over.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function verify(KernelBrowser $client, string $challengeId, string $code, string $ip = '127.0.0.1'): void
    {
        $client->jsonRequest('POST', '/api/v1/auth/login-code/verify', ['challengeId' => $challengeId, 'code' => $code], ['REMOTE_ADDR' => $ip]);
    }

    private static function member(string $email = 'jan@example.org', string $name = 'Jan Bakker', string $displayName = 'Jan B.'): Member
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), $email, $name, $displayName);
    }

    private static function challenge(?Member $member, string $challengeId, string $code): void
    {
        self::service(InMemoryLoginChallengeRepository::class)->add(LoginChallenge::open(
            $challengeId,
            $member?->id(),
            self::service(LoginCodeHasher::class)->hash($challengeId, $code),
            self::service(MockClock::class)->now(),
        ));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function loggedReasons(): array
    {
        return \array_column(\array_column(self::service(RecordingLogger::class)->records, 'context'), 'reason');
    }
}
