<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ChangeMember;

use App\Membership\Domain\Member;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * PATCH /admin/members/{id} as a JSON Merge Patch: a field left out stays, null clears it, a value replaces it.
 */
#[Group('functional')]
final class ChangeMemberEndpointTest extends ApiTestCase
{
    #[Test]
    public function it_keeps_the_fields_left_out(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $piet = self::piet();

        $client->jsonRequest('PATCH', self::path($piet), []);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            ['piet@example.org', 'Piet de Vries', 'Piet V.', '06 1234 5678', 'Dorpsstraat 1', false, []],
            [
                $piet->email()?->value,
                $piet->name(),
                $piet->displayName(),
                $piet->phone()?->value,
                $piet->address(),
                $piet->isCertified(),
                self::service(RecordingAuditTrail::class)->recorded,
            ],
        );
    }

    #[Test]
    public function it_clears_the_phone_number_and_address_set_to_null(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $piet = self::piet();

        $client->jsonRequest('PATCH', self::path($piet), ['phone' => null, 'address' => null]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Piet V.', null, null], [$piet->displayName(), $piet->phone(), $piet->address()]);
    }

    #[Test]
    public function it_refuses_null_for_a_required_field(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $piet = self::piet();

        $client->jsonRequest('PATCH', self::path($piet), ['email' => null, 'certified' => null, 'mcpAllowed' => null]);

        self::assertResponseStatusCodeSame(422);
        /** @var array{violations: list<array{field: string}>} $problem */
        $problem = self::json($client);
        self::assertEqualsCanonicalizing(['email', 'certified', 'mcpAllowed'], \array_column($problem['violations'], 'field'));
    }

    #[Test]
    public function it_replaces_only_the_fields_given(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $piet = self::piet();

        $client->jsonRequest('PATCH', self::path($piet), ['displayName' => 'Piet dV.', 'certified' => true, 'mcpAllowed' => true]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Piet de Vries', 'Piet dV.', '06 1234 5678', true, true], [
            $piet->name(),
            $piet->displayName(),
            $piet->phone()?->value,
            $piet->isCertified(),
            $piet->isMcpAllowed(),
        ]);
    }

    #[Test]
    public function it_refuses_empty_text_for_the_address(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $piet = self::piet();

        $client->jsonRequest('PATCH', self::path($piet), ['address' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Dorpsstraat 1', $piet->address());
    }

    private static function piet(): Member
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'piet@example.org', 'Piet de Vries', 'Piet V.');
    }

    private static function path(Member $member): string
    {
        return '/api/v1/admin/members/' . $member->id()->toRfc4122();
    }
}
