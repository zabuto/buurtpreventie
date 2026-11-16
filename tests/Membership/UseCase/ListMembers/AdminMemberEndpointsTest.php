<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ListMembers;

use App\Identity\Domain\Account;
use App\Membership\Domain\Member;
use App\Shared\Domain\Role;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Identity\Infrastructure\Security\PasswordLoginTestCase;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * /admin/members: the admin views, adds, changes, (de)activates, erases and invites members. Changing roles and
 * erasing need a recent login.
 */
#[Group('functional')]
final class AdminMemberEndpointsTest extends PasswordLoginTestCase
{
    #[Test]
    public function it_lists_deactivated_members_with_their_e_mail_address_for_the_admin(): void
    {
        $client = self::adminClient();
        self::piet()->deactivate();

        $client->request('GET', '/api/v1/admin/members', ['search' => 'Piet']);

        self::assertResponseIsSuccessful();
        /** @var array{members: list<array{email: string|null, active: bool}>} $answer */
        $answer = self::json($client);
        self::assertSame([['piet@example.org', false]], \array_map(
            static fn(array $member): array => [$member['email'], $member['active']],
            $answer['members'],
        ));
    }

    #[Test]
    public function it_refuses_the_admin_list_to_a_coordinator(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);

        $client->request('GET', '/api/v1/admin/members');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_shows_one_member(): void
    {
        $client = self::adminClient();
        $piet = self::piet();

        $client->request('GET', '/api/v1/admin/members/' . $piet->id()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertSame('piet@example.org', self::json($client)['email'] ?? null);
    }

    #[Test]
    public function it_adds_a_member(): void
    {
        $client = self::adminClient();

        $client->jsonRequest('POST', '/api/v1/admin/members', [
            'email' => 'kees@example.org',
            'name' => 'Kees de Wit',
            'displayName' => 'Kees W.',
            'roles' => ['walker'],
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = self::json($client)['id'] ?? null;
        self::assertIsString($id);
        self::assertSame('Kees W.', self::service(InMemoryMemberRepository::class)->get(Uuid::fromString($id))->displayName());
    }

    #[Test]
    public function it_changes_the_member_in_the_path(): void
    {
        $client = self::adminClient();
        $piet = self::piet();

        $client->jsonRequest('PATCH', '/api/v1/admin/members/' . $piet->id()->toRfc4122(), [
            'email' => 'piet@example.org',
            'name' => 'Piet de Vries',
            'displayName' => 'Piet dV.',
            'phone' => null,
            'address' => null,
            'certified' => true,
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Piet dV.', true], [$piet->displayName(), $piet->isCertified()]);
    }

    #[Test]
    public function it_changes_roles_in_a_recent_session(): void
    {
        $client = self::adminClient();
        $piet = self::piet();

        $client->jsonRequest('PUT', self::path($piet, '/roles'), ['roles' => ['walker', 'coordinator']]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([Role::Walker, Role::Coordinator], $piet->roles());
    }

    #[Test]
    public function it_refuses_to_change_roles_in_a_session_that_is_not_recent(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $piet = self::piet();

        $client->jsonRequest('PUT', self::path($piet, '/roles'), ['roles' => ['admin']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:reauthentication_required', self::json($client)['type'] ?? null);
        self::assertSame([Role::Walker], $piet->roles());
    }

    #[Test]
    public function it_deactivates_and_reactivates_a_member(): void
    {
        $client = self::adminClient();
        $piet = self::piet();

        $client->request('POST', self::path($piet, '/deactivation'));
        self::assertResponseStatusCodeSame(204);
        self::assertFalse($piet->isActive());
        $client->request('DELETE', self::path($piet, '/deactivation'));

        self::assertResponseStatusCodeSame(204);
        self::assertTrue($piet->isActive());
    }

    #[Test]
    public function it_erases_a_member_in_a_recent_session(): void
    {
        $client = self::adminClient();
        $piet = self::piet();

        $client->request('DELETE', self::path($piet, ''));

        self::assertResponseStatusCodeSame(204);
        self::assertTrue($piet->isErased());
    }

    #[Test]
    public function it_refuses_to_erase_in_a_session_that_is_not_recent(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $piet = self::piet();

        $client->request('DELETE', self::path($piet, ''));

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($piet->isErased());
    }

    #[Test]
    public function it_gives_the_admin_the_invitation_of_a_member_who_never_logged_in(): void
    {
        $client = self::adminClient();
        $piet = self::piet();

        $client->request('POST', self::path($piet, '/invitation'));

        self::assertResponseStatusCodeSame(201);
        $secret = self::json($client)['secret'] ?? null;
        self::assertIsString($secret);
        self::assertStringStartsWith('bpi_', $secret);
    }

    #[Test]
    public function it_keeps_the_invitation_of_a_member_who_logged_in_before_from_the_admin(): void
    {
        $client = self::adminClient();
        $piet = self::piet();
        $account = Account::open($piet->id());
        $account->recordLogin(new DateTimeImmutable('2026-11-01T12:00:00+00:00'));
        self::service(InMemoryAccountRepository::class)->add($account);

        $client->request('POST', self::path($piet, '/invitation'));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['secret' => null], self::json($client));
    }

    #[Test]
    public function it_keeps_the_invitation_from_a_coordinator(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);
        $piet = self::piet();

        $client->request('POST', self::path($piet, '/invitation'));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['secret' => null], self::json($client));
    }

    /**
     * An admin logged in with the password a moment ago, so the session is recent.
     */
    private static function adminClient(): KernelBrowser
    {
        $client = self::client();
        self::memberWithPassword('admin@example.org', 'Ada Admin', 'Ada A.')->changeRoles([Role::Admin]);
        self::logIn($client, 'admin@example.org', self::PASSWORD);
        self::assertResponseStatusCodeSame(204);

        return $client;
    }

    private static function piet(): Member
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'piet@example.org', 'Piet de Vries', 'Piet V.');
    }

    private static function path(Member $member, string $part): string
    {
        return '/api/v1/admin/members/' . $member->id()->toRfc4122() . $part;
    }
}
