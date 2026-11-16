<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use GdImage;

/**
 * A made-up logo and icon, drawn with GD so no image file sits in the repository: a house in the primary colour with
 * a moon in the accent colour, for the evening rounds. The logo is wide on a transparent background, the icon square
 * and filled, as a phone cuts it to its own shape.
 */
final readonly class SampleBrand
{
    private const int ICON_SIZE = 512;

    public function __construct(
        private string $primary,
        private string $accent,
    ) {
    }

    public function logo(): string
    {
        $image = \imagecreatetruecolor(960, 320);
        \imagealphablending($image, false);
        \imagesavealpha($image, true);
        $transparent = (int) \imagecolorallocatealpha($image, 0, 0, 0, 127);
        \imagefilledrectangle($image, 0, 0, 959, 319, $transparent);

        $primary = self::color($image, $this->primary);
        \imagefilledpolygon($image, [200, 40, 360, 170, 40, 170], $primary);
        \imagefilledrectangle($image, 80, 170, 320, 290, $primary);
        \imagefilledrectangle($image, 175, 215, 225, 290, $transparent);
        \imagefilledellipse($image, 640, 120, 160, 160, self::color($image, $this->accent));
        \imagefilledellipse($image, 690, 90, 150, 150, $transparent);
        \imagefilledrectangle($image, 400, 270, 920, 290, $primary);

        return self::png($image);
    }

    public function icon(): string
    {
        $image = \imagecreatetruecolor(self::ICON_SIZE, self::ICON_SIZE);
        $primary = self::color($image, $this->primary);
        $white = self::color($image, '#ffffff');
        \imagefilledrectangle($image, 0, 0, self::ICON_SIZE - 1, self::ICON_SIZE - 1, $primary);
        \imagefilledpolygon($image, [236, 140, 376, 256, 96, 256], $white);
        \imagefilledrectangle($image, 132, 256, 340, 392, $white);
        \imagefilledrectangle($image, 212, 312, 260, 392, $primary);
        \imagefilledellipse($image, 380, 150, 72, 72, self::color($image, $this->accent));
        \imagefilledellipse($image, 402, 136, 66, 66, $primary);

        return self::png($image);
    }

    private static function png(GdImage $image): string
    {
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
