<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\SetPassword;

use App\Identity\Domain\LoginChallenge;
use App\Identity\Domain\LoginCodeHasher;
use App\Membership\Domain\Member;
use App\Tests\Identity\Fake\InMemoryLoginChallengeRepository;
use App\Tests\Identity\Infrastructure\Security\PasswordLoginTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\MockClock;

/**
 * A forgotten password, the whole way: the member logs in with a code, sets a new password without the old one, and
 * every other session and remembered device ends while the one that set it stays.
 */
#[Group('functional')]
final class SetPasswordControllerTest extends PasswordLoginTestCase
{
    private const string CHALLENGE = '0123456789abcdef0123456789abcdef';
    // @mago-expect lint:no-literal-password - a made-up password the member sets
    private const string NEW_PASSWORD = 'een nieuw lang wachtwoord';

    #[Test]
    public function it_sets_a_new_password_after_a_login_with_a_code_without_the_old_one(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        $jan = self::memberWithPassword();
        self::logInWithCode($client, $jan);

        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD]);

        self::assertResponseStatusCodeSame(204);
        self::logIn($client, 'jan@example.org', self::NEW_PASSWORD);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_takes_the_old_password_no_more(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        self::logInWithCode($client, self::memberWithPassword());
        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD]);

        self::logIn($client, 'jan@example.org', self::PASSWORD);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_keeps_the_session_that_set_it_with_its_remembered_device(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        self::logInWithCode($client, self::memberWithPassword(), rememberMe: true);

        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD]);

        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseIsSuccessful();
        $client->getCookieJar()->expire('MOCKSESSID');
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function it_ends_a_session_on_another_device(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        $jan = self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);
        $otherDevice = self::cookie($client, 'MOCKSESSID');
        $client->getCookieJar()->clear();
        self::logInWithCode($client, $jan);

        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD]);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($otherDevice);
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_ends_a_remembered_device_other_than_the_one_that_set_it(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        $jan = self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD, rememberMe: true);
        $otherDevice = self::cookie($client, 'bp_remember');
        $client->getCookieJar()->clear();
        self::logInWithCode($client, $jan);

        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD]);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($otherDevice);
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_refuses_a_new_password_in_a_session_that_is_not_recent(): void
    {
        $client = self::createClient();
        self::actingAs($client);

        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:reauthentication_required', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_refuses_a_password_shorter_than_15_characters(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        self::logInWithCode($client, self::memberWithPassword());

        // @mago-expect lint:no-literal-password - a made-up password that is too short
        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => 'te kort']);

        self::assertResponseStatusCodeSame(422);
        /** @var array{violations: list<array{field: string}>} $problem */
        $problem = self::json($client);
        self::assertSame(['password'], \array_column($problem['violations'], 'field'));
    }

    #[Test]
    public function it_sets_the_password_of_the_member_acting_whatever_member_the_body_names(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        $piet = self::memberWithPassword('piet@example.org', 'Piet de Vries', 'Piet V.');
        self::logInWithCode($client, self::memberWithPassword());

        $client->jsonRequest('PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD, 'memberId' => $piet->id()->toRfc4122()]);

        self::assertResponseStatusCodeSame(204);
        self::logIn($client, 'piet@example.org', self::PASSWORD);
        self::assertResponseStatusCodeSame(204);
    }

    private static function logInWithCode(KernelBrowser $client, Member $member, bool $rememberMe = false): void
    {
        self::service(InMemoryLoginChallengeRepository::class)->add(LoginChallenge::open(
            self::CHALLENGE,
            $member->id(),
            self::service(LoginCodeHasher::class)->hash(self::CHALLENGE, '482913'),
            self::service(MockClock::class)->now(),
        ));
        $client->jsonRequest('POST', '/api/v1/auth/login-code/verify', ['challengeId' => self::CHALLENGE, 'code' => '482913', 'rememberMe' => $rememberMe]);
        self::assertResponseStatusCodeSame(204);
    }

    private static function cookie(KernelBrowser $client, string $name): Cookie
    {
        $cookie = $client->getCookieJar()->get($name, '/', 'localhost');
        self::assertNotNull($cookie);

        return $cookie;
    }
}
