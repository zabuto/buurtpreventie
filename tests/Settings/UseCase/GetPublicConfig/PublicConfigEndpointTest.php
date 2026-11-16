<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetPublicConfig;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Settings\Domain\Setting;
use App\Shared\Domain\Role;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * GET /config and GET /manifest.webmanifest, both public. The in-memory kernel runs the app under
 * https://example.org/buurtpreventie/, so every path these give proves it keeps the path of the base URL.
 */
#[Group('functional')]
final class PublicConfigEndpointTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up token secret of the test
    private const string SECRET = 'bpt_cdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcd';

    #[Test]
    public function it_gives_a_visitor_the_look_and_texts_without_the_contact_or_the_group(): void
    {
        $client = self::createClient();
        self::configured();

        $client->request('GET', '/api/v1/config');

        self::assertResponseIsSuccessful();
        $config = self::json($client);
        self::assertSame(
            [
                'Eastside Watch',
                ['name' => null, 'email' => null, 'phone' => null],
                ['url' => null, 'service' => null],
            ],
            [$config['associationName'] ?? null, $config['contact'] ?? null, $config['group'] ?? null],
        );
        self::assertIsString($config['welcomeText'] ?? null);
        self::assertStringStartsWith('Welkom bij de buurtpreventie.', $config['welcomeText']);
    }

    #[Test]
    public function it_gives_a_member_with_a_session_the_contact_and_the_group(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::actingAs($client, Role::Walker);
        self::configured();

        $client->request('GET', '/api/v1/config');

        $config = self::json($client);
        self::assertSame(
            [
                ['name' => 'Anna Hughes', 'email' => null, 'phone' => null],
                ['url' => 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv', 'service' => 'whatsapp'],
            ],
            [$config['contact'] ?? null, $config['group'] ?? null],
        );
    }

    #[Test]
    public function it_gives_a_member_with_an_api_token_the_contact(): void
    {
        $client = self::createClient();
        self::configured();
        $jan = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
        self::service(InMemoryApiTokenRepository::class)->add(ApiToken::issue(
            $jan->id(),
            new TokenTerms('Script', [TokenScope::Read], new DateTimeImmutable('2027-11-01T00:00:00+00:00')),
            self::SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        ));

        $client->request('GET', '/api/v1/config', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET]);

        $contact = self::json($client)['contact'] ?? null;
        self::assertIsArray($contact);
        self::assertSame('Anna Hughes', $contact['name'] ?? null);
    }

    /**
     * Review focus 5: an app under a path starts there, and nothing the app writes holds `/public/`.
     */
    #[Test]
    public function it_serves_a_manifest_that_starts_the_app_under_the_path_of_the_base_url(): void
    {
        $client = self::createClient();
        self::configured();

        $client->request('GET', '/api/v1/manifest.webmanifest');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/manifest+json');
        $manifest = self::json($client);
        self::assertSame(
            [
                'Eastside Watch',
                '/buurtpreventie/app/',
                '/buurtpreventie/app/',
                [
                    ['src' => '/buurtpreventie/api/v1/media/brand/icon-192-defa017000000001.png', 'sizes' => '192x192', 'type' => 'image/png'],
                    ['src' => '/buurtpreventie/api/v1/media/brand/icon-512-defa017000000001.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ],
            ],
            [$manifest['name'] ?? null, $manifest['start_url'] ?? null, $manifest['scope'] ?? null, $manifest['icons'] ?? null],
        );
        self::assertStringNotContainsString('/public/', (string) $client->getResponse()->getContent());
    }

    /**
     * Each icon path, taken without the path of the base URL as the web server does before it hands a request to the
     * app, is an image the app serves.
     */
    #[Test]
    public function it_names_icons_in_the_manifest_that_the_app_serves(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('GET', '/api/v1/manifest.webmanifest');
        /** @var list<array{src: string}> $icons */
        $icons = self::json($client)['icons'] ?? [];

        $types = [];
        foreach ($icons as $icon) {
            $client->request('GET', \substr($icon['src'], \strlen('/buurtpreventie')));
            $types[] = [$client->getResponse()->getStatusCode(), $client->getResponse()->headers->get('Content-Type')];
        }

        self::assertSame([[200, 'image/png'], [200, 'image/png']], $types);
    }

    private static function configured(): void
    {
        $settings = self::service(InMemorySettingRepository::class);
        $settings->add(Setting::of('association.name', 'Eastside Watch'));
        $settings->add(Setting::of('contact.name', 'Anna Hughes'));
        $settings->add(Setting::of('community.group_url', 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv'));
    }
}
