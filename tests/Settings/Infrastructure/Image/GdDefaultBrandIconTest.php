<?php

declare(strict_types=1);

namespace App\Tests\Settings\Infrastructure\Image;

use App\Settings\Infrastructure\Image\GdBrandImageConverter;
use App\Settings\Infrastructure\Image\GdDefaultBrandIcon;
use GdImage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GdDefaultBrandIconTest extends TestCase
{
    #[Test]
    public function it_draws_a_square_png_of_every_icon_size(): void
    {
        $icon = new GdDefaultBrandIcon(new GdBrandImageConverter());

        $shapes = \array_map(static function (int $size) use ($icon): ?array {
            $image = \getimagesizefromstring($icon->png($size));

            return false === $image ? null : [$image[0], $image[1], $image['mime']];
        }, [512, 192, 180, 32]);

        $this->assertSame(
            [
                [512, 512, 'image/png'],
                [192, 192, 'image/png'],
                [180, 180, 'image/png'],
                [32,  32,  'image/png'],
            ],
            $shapes,
        );
    }

    /**
     * A phone cuts an icon to a circle or a rounded square; a transparent corner would show as black or white there.
     */
    #[Test]
    public function it_fills_the_whole_square_in_the_default_primary_colour(): void
    {
        $image = \imagecreatefromstring(new GdDefaultBrandIcon(new GdBrandImageConverter())->png(512));
        \assert($image instanceof GdImage, 'The icon is a PNG.');

        $this->assertSame(
            ['#1f5f8b', '#1f5f8b', '#1f5f8b', '#1f5f8b'],
            [self::hex($image, 0, 0), self::hex($image, 511, 0), self::hex($image, 0, 511), self::hex($image, 511, 511)],
        );
    }

    /**
     * The icon is served under a fixed version and cached for a year, so it may not differ between two requests.
     */
    #[Test]
    public function it_draws_the_same_bytes_every_time(): void
    {
        $this->assertSame(
            new GdDefaultBrandIcon(new GdBrandImageConverter())->png(192),
            new GdDefaultBrandIcon(new GdBrandImageConverter())->png(192),
        );
    }

    private static function hex(GdImage $image, int $x, int $y): string
    {
        $color = \imagecolorat($image, $x, $y);
        \assert(false !== $color, 'The pixel lies inside the image.');

        return \sprintf('#%06x', $color & 0xFF_FF_FF);
    }
}
