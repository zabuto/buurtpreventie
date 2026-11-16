<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetBrandImage;

use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\Setting;
use App\Settings\Infrastructure\Image\GdBrandImageConverter;
use App\Settings\Infrastructure\Image\GdDefaultBrandIcon;
use App\Settings\UseCase\GetBrandImage\GetBrandImage;
use App\Settings\UseCase\GetBrandImage\GetBrandImageHandler;
use App\Shared\Domain\NotFound;
use App\Tests\Settings\Fake\InMemoryBrandImages;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Settings\Fake\TestImages;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetBrandImageHandlerTest extends TestCase
{
    private InMemorySettingRepository $settings;
    private InMemoryBrandImages $images;

    #[Override]
    protected function setUp(): void
    {
        $this->settings = new InMemorySettingRepository();
        $this->images = new InMemoryBrandImages();
    }

    #[Test]
    public function it_gives_the_png_of_a_file_the_setting_names(): void
    {
        $version = $this->images->store(BrandImageKind::Icon, TestImages::png(512, 512));
        $this->settings->add(Setting::of('brand.icon', $version));

        $output = $this->handler()(new GetBrandImage('icon-192-' . $version . '.png'));

        $this->assertSame($this->images->files['icon-192-' . $version . '.png'], $output->content);
    }

    #[Test]
    public function it_gives_the_default_icon_while_none_is_uploaded(): void
    {
        $output = $this->handler()(new GetBrandImage('icon-192-' . BrandImageKind::Icon->defaultVersion() . '.png'));

        $this->assertSame(new GdDefaultBrandIcon(new GdBrandImageConverter())->png(192), $output->content);
    }

    #[Test]
    public function it_knows_no_default_icon_once_an_icon_is_uploaded(): void
    {
        $this->settings->add(Setting::of('brand.icon', $this->images->store(BrandImageKind::Icon, TestImages::png(512, 512))));

        $this->expectException(NotFound::class);

        $this->handler()(new GetBrandImage('icon-192-' . BrandImageKind::Icon->defaultVersion() . '.png'));
    }

    /**
     * A previous logo stays readable only until its files go; a file the setting does not name is not served at all,
     * so nothing uploaded and abandoned is public.
     */
    #[Test]
    public function it_knows_no_file_of_an_image_the_setting_does_not_name(): void
    {
        $previous = $this->images->store(BrandImageKind::Logo, TestImages::png(300, 100));
        $this->settings->add(Setting::of('brand.logo', $this->images->store(BrandImageKind::Logo, TestImages::png(301, 100))));

        $this->expectException(NotFound::class);

        $this->handler()(new GetBrandImage('logo-512-' . $previous . '.png'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignNames(): iterable
    {
        yield 'a path upwards' => ['../../.env.local'];
        yield 'another file in the folder' => ['.upload-AbC123'];
        yield 'a size the logo does not have' => ['logo-192-0123456789abcdef.png'];
    }

    #[Test]
    #[DataProvider('foreignNames')]
    public function it_knows_no_name_outside_the_pattern(string $name): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new GetBrandImage($name));
    }

    private function handler(): GetBrandImageHandler
    {
        return new GetBrandImageHandler($this->settings, $this->images, new GdDefaultBrandIcon(new GdBrandImageConverter()));
    }
}
