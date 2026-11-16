<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Image;

use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\BrandPalette;
use App\Settings\Domain\DefaultBrandIcon;
use GdImage;
use Override;

/**
 * A white house on the default primary colour, filling the square, so a phone can cut it to any shape. The house
 * stays inside the middle 80 %, the part a round mask always shows. Drawn at 512 pixels and scaled down by the
 * converter, which smooths the edges GD draws without antialiasing.
 */
final readonly class GdDefaultBrandIcon implements DefaultBrandIcon
{
    private const int SIZE = 512;

    public function __construct(
        private GdBrandImageConverter $converter,
    ) {
    }

    #[Override]
    public function png(int $size): string
    {
        return $this->converter->convert(BrandImageKind::Icon, $this->drawing())[$size];
    }

    private function drawing(): string
    {
        $image = \imagecreatetruecolor(self::SIZE, self::SIZE);
        $background = self::color($image, BrandPalette::default()->primary->hex);
        $house = self::color($image, '#ffffff');

        \imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $background);
        \imagefilledpolygon($image, [256, 120, 408, 250, 104, 250], $house);
        \imagefilledrectangle($image, 148, 250, 364, 396, $house);
        \imagefilledrectangle($image, 232, 306, 280, 396, $background);

        $stream = \fopen('php://memory', 'w+b');
        \assert(false !== $stream, 'A memory stream always opens.');
        \imagepng($image, $stream);
        \rewind($stream);

        return (string) \stream_get_contents($stream);
    }

    private static function color(GdImage $image, string $hex): int
    {
        $rgb = (int) \hexdec(\ltrim($hex, '#'));

        return (int) \imagecolorallocate($image, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
