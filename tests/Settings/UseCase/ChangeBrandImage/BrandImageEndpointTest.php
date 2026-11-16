<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\ChangeBrandImage;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\Setting;
use App\Shared\Domain\Role;
use App\Tests\Settings\Fake\InMemoryBrandImages;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Settings\Fake\TestImages;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * PUT and DELETE /admin/settings/images/{kind}, and GET /media/brand/{file}, which serves what an upload stored.
 */
#[Group('functional')]
final class BrandImageEndpointTest extends ApiTestCase
{
    /**
     * The client calls it a PNG named logo.png; the content is a JPEG, and the content decides.
     */
    #[Test]
    public function it_stores_an_upload_by_its_content_and_serves_each_size_as_a_cacheable_png(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::actingAs($client, Role::Admin);
        $upload = TestImages::jpeg(900, 300);

        $client->request(
            'PUT',
            '/api/v1/admin/settings/images/logo',
            server: ['CONTENT_TYPE' => 'image/png', 'HTTP_CONTENT_DISPOSITION' => 'attachment; filename="logo.png"'],
            content: $upload,
        );
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/v1/media/brand/logo-128-' . BrandImageFile::versionOf($upload) . '.png');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        $size = \getimagesizefromstring((string) $response->getContent());
        self::assertSame(
            ['image/png', 'immutable, max-age=31536000, public', 'nosniff', [384, 128]],
            [
                $response->headers->get('Content-Type'),
                $response->headers->get('Cache-Control'),
                $response->headers->get('X-Content-Type-Options'),
                false === $size ? null : [$size[0], $size[1]],
            ],
        );
    }

    #[Test]
    public function it_refuses_an_upload_that_is_not_a_png_or_jpeg_with_the_condition(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);

        $client->request('PUT', '/api/v1/admin/settings/images/icon', server: ['CONTENT_TYPE' => 'image/png'], content: TestImages::gif(512, 512));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['urn:buurtpreventie:problem:unsupported_image', 'format'],
            [self::json($client)['type'] ?? null, self::json($client)['condition'] ?? null],
        );
    }

    #[Test]
    public function it_refuses_an_upload_over_2_mb_without_reading_all_of_it(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);

        $client->request('PUT', '/api/v1/admin/settings/images/logo', content: TestImages::png(10, 10) . \str_repeat("\0", 3 * 1024 * 1024));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('file_size', self::json($client)['condition'] ?? null);
    }

    #[Test]
    public function it_puts_the_default_back_and_stops_serving_the_upload(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::actingAs($client, Role::Admin);
        $version = self::service(InMemoryBrandImages::class)->store(BrandImageKind::Icon, TestImages::png(512, 512));
        self::service(InMemorySettingRepository::class)->add(Setting::of('brand.icon', $version));

        $client->request('DELETE', '/api/v1/admin/settings/images/icon');
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/v1/media/brand/icon-192-' . $version . '.png');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function it_knows_no_kind_of_image_besides_logo_and_icon(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);

        $client->request('PUT', '/api/v1/admin/settings/images/favicon', content: TestImages::png(32, 32));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function it_serves_no_file_outside_the_names_of_the_settings(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/media/brand/..%2F..%2F.env.local');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The firewall reads the session of a logged-in member, after which Symfony would mark any answer private.
     */
    #[Test]
    public function it_lets_the_image_be_cached_for_a_logged_in_member_too(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::actingAs($client, Role::Walker);
        $version = self::service(InMemoryBrandImages::class)->store(BrandImageKind::Logo, TestImages::png(300, 100));
        self::service(InMemorySettingRepository::class)->add(Setting::of('brand.logo', $version));

        $client->request('GET', '/api/v1/media/brand/logo-512-' . $version . '.png');

        self::assertSame(
            [200, 'immutable, max-age=31536000, public', false],
            [
                $client->getResponse()->getStatusCode(),
                $client->getResponse()->headers->get('Cache-Control'),
                $client->getResponse()->headers->has('Symfony-Session-NoAutoCacheControl'),
            ],
        );
    }
}
