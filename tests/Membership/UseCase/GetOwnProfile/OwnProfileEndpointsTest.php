<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\GetOwnProfile;

use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * GET and PATCH /me: the member reads their own profile and changes what is theirs to change.
 */
#[Group('functional')]
final class OwnProfileEndpointsTest extends ApiTestCase
{
    #[Test]
    public function it_shows_the_profile_of_the_member_logged_in(): void
    {
        $client = self::createClient();
        $member = self::actingAs($client);

        $client->request('GET', '/api/v1/me');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                'id' => $member->id()->toRfc4122(),
                'email' => 'ingelogd@example.org',
                'name' => 'Ingelogd Lid',
                'displayName' => 'Ingelogd L.',
                'phone' => '06 1234 5678',
                'address' => 'Dorpsstraat 1',
                'roles' => ['walker'],
                'consentContactShare' => false,
                'notifyOpenRounds' => false,
                'certified' => false,
            ],
            self::json($client),
        );
    }

    #[Test]
    public function it_refuses_a_visitor_who_is_not_logged_in(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/me');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_changes_contact_details_and_choices_in_one_request(): void
    {
        $client = self::createClient();
        $member = self::actingAs($client);

        $client->jsonRequest('PATCH', '/api/v1/me', ['phone' => '020 123 4567', 'consentContactShare' => true, 'notifyOpenRounds' => true]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['020 123 4567', 'Dorpsstraat 1', true, true], [
            $member->phone()?->value,
            $member->address(),
            $member->consentsToContactShare(),
            $member->wantsOpenRoundNotifications(),
        ]);
    }

    /**
     * A hijacked session could otherwise move the address a login code goes to.
     */
    #[Test]
    public function it_ignores_a_new_name_or_e_mail_address(): void
    {
        $client = self::createClient();
        $member = self::actingAs($client);

        $client->jsonRequest('PATCH', '/api/v1/me', ['email' => 'kaper@example.org', 'name' => 'Iemand Anders']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['ingelogd@example.org', 'Ingelogd Lid', []], [
            $member->email()?->value,
            $member->name(),
            self::service(RecordingAuditTrail::class)->recorded,
        ]);
    }

    #[Test]
    public function it_refuses_an_invalid_phone_number(): void
    {
        $client = self::createClient();
        self::actingAs($client);

        $client->jsonRequest('PATCH', '/api/v1/me', ['phone' => 'bel me maar']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_phone_number', self::json($client)['type'] ?? null);
    }
}
