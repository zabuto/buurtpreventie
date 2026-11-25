<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\ChangeSettings;

use App\Settings\Domain\Setting;
use App\Shared\Domain\Role;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * PATCH and GET /admin/settings: a JSON Merge Patch over every setting, and what the admin reads back.
 */
#[Group('functional')]
final class ChangeSettingsEndpointTest extends ApiTestCase
{
    #[Test]
    public function it_changes_what_the_patch_holds_and_shows_it_back(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::actingAs($client, Role::Admin);
        self::stored('contact.name', 'Anna de Vries');

        $client->jsonRequest('PATCH', '/api/v1/admin/settings', [
            'associationName' => 'Buurtpreventie Oost',
            'primaryColor' => '#0B4F6C',
            'contactName' => null,
            'reminderDaysAhead' => 2,
            'apiAccess' => false,
            'mcpAccess' => true,
            'mcpRoles' => ['walker', 'analyst', 'walker'],
        ]);
        self::assertResponseStatusCodeSame(204);
        $client->jsonRequest('GET', '/api/v1/admin/settings');

        self::assertResponseIsSuccessful();
        /** @var array<string, mixed> $settings */
        $settings = self::json($client);
        self::assertSame(
            ['Buurtpreventie Oost', '#0b4f6c', null, 2, false, true, ['analyst', 'walker'], 'Buurtpreventie'],
            [
                $settings['associationName'],
                $settings['primaryColor'],
                $settings['contactName'],
                $settings['reminderDaysAhead'],
                $settings['apiAccess'],
                $settings['mcpAccess'],
                $settings['mcpRoles'],
                $settings['associationShortName'],
            ],
        );
        self::assertIsString($settings['welcomeTextNl']);
        self::assertStringStartsWith('Welkom bij de buurtpreventie.', $settings['welcomeTextNl']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty name' => [['associationName' => ''], 'associationName'];
        yield 'short name too long' => [['associationShortName' => \str_repeat('a', 31)], 'associationShortName'];
        yield 'colour by name' => [['accentColor' => 'orange'], 'accentColor'];
        yield 'e-mail address without domain' => [['contactEmail' => 'anna'], 'contactEmail'];
        yield 'privacy page over http' => [['privacyUrl' => 'http://example.org/privacy'], 'privacyUrl'];
        yield 'reminder too far ahead' => [['reminderDaysAhead' => 8], 'reminderDaysAhead'];
        yield 'language without texts' => [['language' => 'de'], 'language'];
        yield 'text for a switch' => [['apiAccess' => 'off'], 'apiAccess'];
        yield 'no role for MCP' => [['mcpRoles' => []], 'mcpRoles'];
        yield 'role for MCP that does not exist' => [['mcpRoles' => ['walker', 'member']], 'mcpRoles[1]'];
        yield 'text for the roles for MCP' => [['mcpRoles' => 'walker'], 'mcpRoles'];
    }

    /**
     * @param array<string, mixed> $patch
     */
    #[Test]
    #[DataProvider('malformed')]
    public function it_refuses_a_malformed_field_by_name(array $patch, string $field): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);

        $client->jsonRequest('PATCH', '/api/v1/admin/settings', $patch);

        self::assertResponseStatusCodeSame(422);
        /** @var array{violations: list<array{field: string}>} $problem */
        $problem = self::json($client);
        self::assertSame([$field], \array_column($problem['violations'], 'field'));
        self::assertSame([], self::service(InMemorySettingRepository::class)->all());
    }

    #[Test]
    public function it_explains_a_colour_without_enough_contrast(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);

        $client->jsonRequest('PATCH', '/api/v1/admin/settings', ['associationName' => 'Buurtpreventie Oost', 'primaryDarkColor' => '#7c7c7c']);

        self::assertResponseStatusCodeSame(422);
        /** @var array<string, mixed> $problem */
        $problem = self::json($client);
        self::assertSame(
            ['urn:buurtpreventie:problem:insufficient_contrast', 'brand.primary_dark', 4.48, 4.5],
            [$problem['type'], $problem['setting'], $problem['ratio'], $problem['required']],
        );
        self::assertSame([], self::service(InMemorySettingRepository::class)->all());
    }

    #[Test]
    public function it_refuses_a_link_to_another_site_as_the_group(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);

        $client->jsonRequest('PATCH', '/api/v1/admin/settings', ['groupUrl' => 'https://example.org/group']);

        self::assertResponseStatusCodeSame(422);
        /** @var array<string, mixed> $problem */
        $problem = self::json($client);
        self::assertSame('urn:buurtpreventie:problem:invalid_group_link', $problem['type']);
    }

    private static function stored(string $name, string $value): void
    {
        self::service(InMemorySettingRepository::class)->add(Setting::of($name, $value));
    }
}
