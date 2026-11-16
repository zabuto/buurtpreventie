<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\GetMemberSummaries;

use App\Membership\Domain\Member;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * GET /members and review focus 3: a member without consent never shows a phone number, e-mail address or address,
 * neither in the member list nor in a round.
 */
#[Group('functional')]
final class MemberListEndpointsTest extends ApiTestCase
{
    private const array CONTACT_DETAILS = ['06 1234 5678', 'Dorpsstraat 1', 'piet@example.org'];

    /**
     * @return iterable<string, array{Role}>
     */
    public static function readers(): iterable
    {
        yield 'a walker' => [Role::Walker];
        yield 'a coordinator' => [Role::Coordinator];
    }

    #[DataProvider('readers')]
    #[Test]
    public function it_shows_no_contact_details_of_a_member_without_consent_in_the_list(Role $role): void
    {
        $client = self::client();
        self::actingAs($client, $role);
        self::piet();

        $client->request('GET', '/api/v1/members');

        self::assertResponseIsSuccessful();
        self::assertContactDetailsHidden($client);
    }

    #[Test]
    public function it_shows_phone_and_address_with_consent_but_never_the_email_address(): void
    {
        $client = self::client();
        self::actingAs($client);
        self::piet()->changeContactConsent(true);

        $client->request('GET', '/api/v1/members', ['search' => 'Piet']);

        self::assertResponseIsSuccessful();
        self::assertSame(
            [['displayName' => 'Piet V.', 'name' => null, 'phone' => '06 1234 5678', 'address' => 'Dorpsstraat 1']],
            \array_map(static fn(array $member): array => \array_diff_key($member, ['id' => true]), self::members($client)),
        );
    }

    #[Test]
    public function it_leaves_deactivated_and_erased_members_out(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);
        self::piet()->deactivate();
        MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'kees@example.org', 'Kees de Wit', 'Kees W.')
            ->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'))
        ;

        $client->request('GET', '/api/v1/members');

        self::assertSame(['Ingelogd L.'], \array_column(self::members($client), 'displayName'));
    }

    #[DataProvider('readers')]
    #[Test]
    public function it_shows_no_contact_details_of_a_walker_without_consent_in_a_round(Role $role): void
    {
        $client = self::client();
        $member = self::actingAs($client, $role, Role::Walker);
        $round = RoundFixture::withWalkers(self::piet()->id(), 1);
        $round->signUp($member->id(), new DateTimeImmutable(RoundFixture::CREATED_AT));
        self::service(InMemoryRoundRepository::class)->add($round);

        $client->request('GET', '/api/v1/rounds/' . $round->id()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertContactDetailsHidden($client);
    }

    #[Test]
    public function it_refuses_a_visitor_who_is_not_logged_in(): void
    {
        $client = self::client();

        $client->request('GET', '/api/v1/members');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * One kernel for every request of the test, so the members stored before the first request stay.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * Has a phone number and an address, and no consent to share them unless the test gives it.
     */
    private static function piet(): Member
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'piet@example.org', 'Piet de Vries', 'Piet V.');
    }

    private static function assertContactDetailsHidden(KernelBrowser $client): void
    {
        $body = (string) $client->getResponse()->getContent();
        foreach (self::CONTACT_DETAILS as $detail) {
            self::assertStringNotContainsString($detail, $body);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function members(KernelBrowser $client): array
    {
        /** @var array{members: list<array<string, mixed>>} $answer */
        $answer = self::json($client);

        return $answer['members'];
    }
}
