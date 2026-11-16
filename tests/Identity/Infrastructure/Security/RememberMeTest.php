<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\PasswordHasher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\MockClock;

/**
 * "Stay logged in": a signed cookie that starts a new session for 90 days, until the member sets a new password.
 */
#[Group('functional')]
final class RememberMeTest extends PasswordLoginTestCase
{
    #[Test]
    public function it_sets_a_secure_http_only_cookie_for_90_days_when_the_member_asks(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        self::memberWithPassword();

        self::logIn($client, 'jan@example.org', self::PASSWORD, rememberMe: true);

        $cookie = self::rememberMeCookie($client);
        self::assertNotNull($cookie);
        self::assertSame([true, true, 'lax', '/'], [
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            \strtolower((string) $cookie->getSameSite()),
            $cookie->getPath(),
        ]);
        $days = ((int) $cookie->getExpiresTime() - \time()) / 86_400;
        self::assertTrue($days > 89.9 && $days <= 90, \sprintf('The cookie lasts %.2f days', $days));
    }

    #[Test]
    public function it_sets_no_cookie_without_the_wish(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        self::memberWithPassword();

        self::logIn($client, 'jan@example.org', self::PASSWORD, rememberMe: false);

        self::assertResponseStatusCodeSame(204);
        self::assertNull(self::rememberMeCookie($client));
    }

    #[Test]
    public function it_starts_a_new_session_on_the_web_channel_from_the_cookie_that_is_not_recent(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        $jan = self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD, rememberMe: true);

        self::endSession($client);
        $client->request('GET', '/api/v1/_probe-identity');

        self::assertResponseIsSuccessful();
        self::assertSame(['memberId' => $jan->id()->toRfc4122(), 'roles' => ['walker'], 'channel' => 'web', 'recent' => false], self::json($client));
    }

    #[Test]
    public function it_takes_the_cookie_no_more_once_the_member_sets_a_new_password(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        $jan = self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD, rememberMe: true);

        self::service(MockClock::class)->modify('+1 day');
        self::account($jan)->changePassword(self::service(PasswordHasher::class)->hash('een ander lang wachtwoord'), self::service(MockClock::class)->now());
        self::endSession($client);
        $client->request('GET', '/api/v1/_probe-identity');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_clears_the_cookie_at_logout(): void
    {
        $client = self::client(['HTTPS' => 'on']);
        self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD, rememberMe: true);

        $client->request('POST', '/api/v1/auth/logout');

        self::assertResponseStatusCodeSame(204);
        self::assertNull(self::rememberMeCookie($client));
    }

    /**
     * As when the browser closes; the test environment names its mock session cookie MOCKSESSID.
     */
    private static function endSession(KernelBrowser $client): void
    {
        $client->getCookieJar()->expire('MOCKSESSID');
    }

    private static function rememberMeCookie(KernelBrowser $client): ?Cookie
    {
        return $client->getCookieJar()->get('bp_remember', '/', 'localhost');
    }
}
