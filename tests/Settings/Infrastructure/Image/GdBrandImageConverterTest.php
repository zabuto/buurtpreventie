<?php

declare(strict_types=1);

namespace App\Tests\Settings\Infrastructure\Image;

use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\UnsupportedImage;
use App\Settings\Infrastructure\Image\GdBrandImageConverter;
use App\Tests\Settings\Fake\TestImages;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GdBrandImageConverterTest extends TestCase
{
    #[Test]
    public function it_turns_a_logo_into_a_png_for_the_screen_and_one_for_mail(): void
    {
        $renditions = new GdBrandImageConverter()->convert(BrandImageKind::Logo, TestImages::jpeg(1200, 400));

        $this->assertSame([512 => [512, 171, 'image/png'], 128 => [384, 128, 'image/png']], self::shapes($renditions));
    }

    #[Test]
    public function it_turns_an_icon_into_square_pngs_of_every_size(): void
    {
        $renditions = new GdBrandImageConverter()->convert(BrandImageKind::Icon, TestImages::png(1024, 1024));

        $this->assertSame(
            [512 => [512, 512, 'image/png'], 192 => [192, 192, 'image/png'], 180 => [180, 180, 'image/png'], 32 => [32, 32, 'image/png']],
            self::shapes($renditions),
        );
    }

    #[Test]
    public function it_keeps_the_transparency_of_a_png(): void
    {
        $renditions = new GdBrandImageConverter()->convert(BrandImageKind::Logo, TestImages::png(300, 100));

        $image = \imagecreatefromstring($renditions[512]);
        \assert(false !== $image, 'The converter writes PNG.');
        $this->assertSame([127, 0], [self::alpha($image, 0, 0), self::alpha($image, 50, 50)]);
    }

    /**
     * @return int 127 for fully transparent, 0 for opaque
     */
    private static function alpha(GdImage $image, int $x, int $y): int
    {
        $color = \imagecolorat($image, $x, $y);
        \assert(false !== $color, 'The pixel lies inside the image.');

        $alpha = \imagecolorsforindex($image, $color)['alpha'];
        // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType (PHPStan reads the type from its stubs, Mago does not)
        \assert(\is_int($alpha), 'GD gives every channel as a number.');

        return $alpha;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function imagesWithHiddenContent(): iterable
    {
        yield 'the place a photo was taken' => [TestImages::jpegWithLocation(600, 600)];
        yield 'a script after the end of a PNG' => [TestImages::pngWithPayload(600, 600)];
    }

    #[Test]
    #[DataProvider('imagesWithHiddenContent')]
    public function it_stores_only_the_pixels_of_an_image(string $upload): void
    {
        $renditions = new GdBrandImageConverter()->convert(BrandImageKind::Icon, $upload);

        $this->assertSame([], \array_filter($renditions, static fn(string $png): bool => \str_contains($png, TestImages::MARKER)));
    }

    /**
     * @return iterable<string, array{BrandImageKind, string, string}>
     */
    public static function refusedUploads(): iterable
    {
        yield 'nothing' => [BrandImageKind::Logo, '', 'format'];
        yield 'text' => [BrandImageKind::Logo, 'Buurtpreventie', 'format'];
        yield 'an SVG' => [BrandImageKind::Logo, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'format'];
        yield 'a GIF' => [BrandImageKind::Logo, TestImages::gif(100, 100), 'format'];
        yield 'a WebP' => [BrandImageKind::Icon, TestImages::webp(512, 512), 'format'];
        yield 'a PNG cut short' => [BrandImageKind::Logo, \substr(TestImages::png(600, 600), 0, 200), 'format'];
        yield 'more than 2 MB' => [BrandImageKind::Logo, TestImages::png(10, 10) . \str_repeat("\0", 2 * 1024 * 1024), 'file_size'];
        yield 'more than 2048 pixels wide' => [BrandImageKind::Logo, TestImages::png(2049, 1), 'too_large'];
        yield 'an icon that is not square' => [BrandImageKind::Icon, TestImages::png(600, 500), 'not_square'];
        yield 'an icon under 512 pixels' => [BrandImageKind::Icon, TestImages::png(256, 256), 'too_small'];
    }

    #[Test]
    #[DataProvider('refusedUploads')]
    public function it_refuses_an_upload_and_names_the_condition_it_breaks(BrandImageKind $kind, string $upload, string $condition): void
    {
        $refusal = null;
        try {
            new GdBrandImageConverter()->convert($kind, $upload);
        } catch (UnsupportedImage $exception) {
            $refusal = $exception;
        }

        $this->assertSame(['condition' => $condition], $refusal?->details());
    }

    /**
     * @param array<int, string> $renditions
     *
     * @return array<int, array{int, int, string}>
     */
    private static function shapes(array $renditions): array
    {
        return \array_map(static function (string $png): array {
            $size = \getimagesizefromstring($png);
            \assert(false !== $size, 'The converter writes images.');

            return [$size[0], $size[1], $size['mime']];
        }, $renditions);
    }
}
