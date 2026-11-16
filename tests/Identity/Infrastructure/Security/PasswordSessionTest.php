<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\PasswordHasher;
use App\Shared\Domain\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Clock\MockClock;

/**
 * What happens to a session after a password login: how long it is recent, how a change to the member reaches it, and
 * how it ends.
 */
#[Group('functional')]
final class PasswordSessionTest extends PasswordLoginTestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function momentsAfterTheLogin(): iterable
    {
        yield 'at 15 minutes' => ['+15 minutes', true];
        yield 'a second later' => ['+15 minutes 1 second', false];
    }

    /**
     * A sensitive action asks for a recent proof; the session itself holds on.
     */
    #[DataProvider('momentsAfterTheLogin')]
    #[Test]
    public function it_counts_the_login_as_recent_for_15_minutes(string $later, bool $recent): void
    {
        $client = self::client();
        self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);

        self::service(MockClock::class)->modify($later);
        $client->request('GET', '/api/v1/_probe-identity');

        self::assertResponseIsSuccessful();
        self::assertSame($recent, self::json($client)['recent'] ?? null);
    }

    #[Test]
    public function it_logs_out_a_member_who_was_deactivated_on_the_next_request(): void
    {
        $client = self::client();
        $jan = self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);

        $jan->deactivate();
        $client->request('GET', '/api/v1/_probe-identity');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_logs_out_a_session_on_the_next_request_once_the_member_sets_a_new_password_elsewhere(): void
    {
        $client = self::client();
        $jan = self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);

        self::account($jan)->changePassword(self::service(PasswordHasher::class)->hash('een ander lang wachtwoord'), self::service(MockClock::class)->now());
        $client->request('GET', '/api/v1/_probe-identity');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_applies_a_changed_role_on_the_next_request_without_logging_out(): void
    {
        $client = self::client();
        $jan = self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);

        $jan->changeRoles([Role::Walker, Role::Coordinator]);
        $client->request('GET', '/api/v1/_probe-identity');

        self::assertSame(['walker', 'coordinator'], self::json($client)['roles'] ?? null);
    }

    #[Test]
    public function it_ends_the_session_and_clears_its_cookie_at_logout(): void
    {
        $client = self::client();
        self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);

        $client->request('POST', '/api/v1/auth/logout');

        self::assertResponseStatusCodeSame(204);
        self::assertStringContainsString('bp_session=deleted', (string) $client->getResponse()->headers->get('Set-Cookie'));
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_has_no_logout_by_get(): void
    {
        $client = self::client();
        self::memberWithPassword();
        self::logIn($client, 'jan@example.org', self::PASSWORD);

        $client->request('GET', '/api/v1/auth/logout');

        self::assertResponseStatusCodeSame(405);
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertResponseIsSuccessful();
    }
}
