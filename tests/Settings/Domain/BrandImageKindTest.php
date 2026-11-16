<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\ImageFacts;
use App\Settings\Domain\UnsupportedImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrandImageKindTest extends TestCase
{
    private const int TWO_MEGABYTES = 2 * 1024 * 1024;

    /**
     * @return iterable<string, array{BrandImageKind, ImageFacts}>
     */
    public static function acceptedImages(): iterable
    {
        yield 'a wide PNG logo' => [BrandImageKind::Logo, new ImageFacts(40_000, 'image/png', 'image/png', 2048, 300)];
        yield 'a tiny JPEG logo' => [BrandImageKind::Logo, new ImageFacts(900, 'image/jpeg', 'image/jpeg', 16, 16)];
        yield 'a logo of exactly 2 MB' => [BrandImageKind::Logo, new ImageFacts(self::TWO_MEGABYTES, 'image/png', 'image/png', 600, 200)];
        yield 'the smallest icon' => [BrandImageKind::Icon, new ImageFacts(20_000, 'image/png', 'image/png', 512, 512)];
        yield 'the largest icon' => [BrandImageKind::Icon, new ImageFacts(20_000, 'image/jpeg', 'image/jpeg', 2048, 2048)];
    }

    #[Test]
    #[DataProvider('acceptedImages')]
    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function it_accepts_a_png_or_jpeg_within_the_limits_of_its_kind(BrandImageKind $kind, ImageFacts $image): void
    {
        $kind->check($image);
    }

    /**
     * @return iterable<string, array{BrandImageKind, ImageFacts, string}>
     */
    public static function refusedImages(): iterable
    {
        yield 'a byte over 2 MB' => [BrandImageKind::Logo, new ImageFacts(self::TWO_MEGABYTES + 1, 'image/png', 'image/png', 600, 200), 'file_size'];
        yield 'not an image' => [BrandImageKind::Logo, new ImageFacts(500, null, 'text/plain', 0, 0), 'format'];
        yield 'a GIF' => [BrandImageKind::Logo, new ImageFacts(500, 'image/gif', 'image/gif', 100, 100), 'format'];
        yield 'a WebP' => [BrandImageKind::Icon, new ImageFacts(500, 'image/webp', 'image/webp', 512, 512), 'format'];
        yield 'an SVG' => [BrandImageKind::Logo, new ImageFacts(500, null, 'image/svg+xml', 0, 0), 'format'];
        yield 'a PNG header on other content' => [BrandImageKind::Logo, new ImageFacts(500, 'image/png', 'application/zip', 100, 100), 'format'];
        yield 'a logo wider than 2048' => [BrandImageKind::Logo, new ImageFacts(500, 'image/png', 'image/png', 2049, 10), 'too_large'];
        yield 'a logo higher than 2048' => [BrandImageKind::Logo, new ImageFacts(500, 'image/png', 'image/png', 10, 2049), 'too_large'];
        yield 'an icon larger than 2048' => [BrandImageKind::Icon, new ImageFacts(500, 'image/png', 'image/png', 2049, 2049), 'too_large'];
        yield 'an icon that is not square' => [BrandImageKind::Icon, new ImageFacts(500, 'image/png', 'image/png', 1024, 1023), 'not_square'];
        yield 'an icon under 512' => [BrandImageKind::Icon, new ImageFacts(500, 'image/png', 'image/png', 511, 511), 'too_small'];
    }

    #[Test]
    #[DataProvider('refusedImages')]
    public function it_refuses_an_image_and_names_the_condition_it_breaks(BrandImageKind $kind, ImageFacts $image, string $condition): void
    {
        $refusal = null;
        try {
            $kind->check($image);
        } catch (UnsupportedImage $exception) {
            $refusal = $exception;
        }

        $this->assertSame(['condition' => $condition], $refusal?->details());
    }

    /**
     * A web app needs an icon to install; without a logo the app shows the name of the association instead.
     */
    #[Test]
    public function it_has_a_default_icon_but_no_default_logo(): void
    {
        $this->assertSame([true, null], [BrandImageFile::isVersion((string) BrandImageKind::Icon->defaultVersion()), BrandImageKind::Logo->defaultVersion()]);
    }

    #[Test]
    public function it_derives_a_logo_for_the_screen_and_one_for_mail(): void
    {
        $sizes = \array_map(static fn(\App\Settings\Domain\Rendition $rendition): array => [
            $rendition->size,
            $rendition->maxWidth,
            $rendition->maxHeight,
        ], BrandImageKind::Logo->renditions());

        $this->assertSame([[512, 512, 512], [128, 512, 128]], $sizes);
    }

    #[Test]
    public function it_derives_the_icon_sizes_of_a_web_app_an_apple_home_screen_and_a_favicon(): void
    {
        $sizes = \array_map(static fn(\App\Settings\Domain\Rendition $rendition): int => $rendition->size, BrandImageKind::Icon->renditions());

        $this->assertSame([512, 192, 180, 32], $sizes);
    }
}
