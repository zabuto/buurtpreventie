<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetWebManifest;

use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetWebManifest\GetWebManifest;
use App\Settings\UseCase\GetWebManifest\GetWebManifestHandler;
use App\Settings\UseCase\GetWebManifest\GetWebManifestOutput;
use App\Shared\Domain\AppUrl;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetWebManifestHandlerTest extends TestCase
{
    /**
     * Start and scope follow the base URL, not the place of the manifest under the API, so a phone opens the app and
     * keeps every page of it inside the installed app.
     */
    #[Test]
    public function it_names_the_app_after_the_association_and_starts_it_under_the_path_of_the_base_url(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('association.name', 'Eastside Watch'));
        $settings->add(Setting::of('association.short_name', 'Eastside'));
        $settings->add(Setting::of('brand.primary', '#0b4f6c'));
        $settings->add(Setting::of('brand.icon', 'fedcba9876543210'));

        $manifest = new GetWebManifestHandler($settings, AppUrl::fromString('https://example.org/buurtpreventie'))(new GetWebManifest());

        $this->assertEquals(
            new GetWebManifestOutput(
                name: 'Eastside Watch',
                shortName: 'Eastside',
                language: 'nl',
                startUrl: '/buurtpreventie/app/',
                scope: '/buurtpreventie/app/',
                display: 'standalone',
                themeColor: '#0b4f6c',
                backgroundColor: '#ffffff',
                icons: [
                    ['src' => '/buurtpreventie/api/v1/media/brand/icon-192-fedcba9876543210.png', 'sizes' => '192x192', 'type' => 'image/png'],
                    ['src' => '/buurtpreventie/api/v1/media/brand/icon-512-fedcba9876543210.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ],
            ),
            $manifest,
        );
    }

    #[Test]
    public function it_gives_the_default_icon_while_none_is_uploaded(): void
    {
        $manifest = new GetWebManifestHandler(new InMemorySettingRepository(), AppUrl::fromString('https://buurt.example.org'))(new GetWebManifest());

        $this->assertSame(
            ['/api/v1/media/brand/icon-192-defa017000000001.png', '/api/v1/media/brand/icon-512-defa017000000001.png', '/app/'],
            [$manifest->icons[0]['src'], $manifest->icons[1]['src'], $manifest->startUrl],
        );
    }
}
