<?php

declare(strict_types=1);

namespace App\Tests\Settings\Fake;

use GdImage;

/**
 * Small images drawn with GD in memory, so a test shows the size and kind of image it uploads.
 */
final readonly class TestImages
{
    /** The text a hidden payload carries, so a test can look for it in what the app stores. */
    public const string MARKER = 'GPS 52.3731 N 4.8922 E';

    /**
     * A PNG whose top-left pixel is fully transparent and the rest opaque blue.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function png(int $width, int $height): string
    {
        $image = self::canvas($width, $height);
        \imagesavealpha($image, true);
        \imagealphablending($image, false);
        \imagesetpixel($image, 0, 0, (int) \imagecolorallocatealpha($image, 0, 0, 0, 127));

        return self::encoded(static fn($stream): bool => \imagepng($image, $stream));
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function jpeg(int $width, int $height): string
    {
        return self::encoded(static fn($stream): bool => \imagejpeg(self::canvas($width, $height), $stream));
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function gif(int $width, int $height): string
    {
        return self::encoded(static fn($stream): bool => \imagegif(self::canvas($width, $height), $stream));
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function webp(int $width, int $height): string
    {
        return self::encoded(static fn($stream): bool => \imagewebp(self::canvas($width, $height), $stream));
    }

    /**
     * A JPEG with an Exif segment right after its start marker, where a phone puts the place a photo was taken.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function jpegWithLocation(int $width, int $height): string
    {
        $exif = "Exif\0\0" . self::MARKER;

        return "\xFF\xD8\xFF\xE1" . \pack('n', \strlen($exif) + 2) . $exif . \substr(self::jpeg($width, $height), 2);
    }

    /**
     * A PNG that is also something else after its end, such as a script.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function pngWithPayload(int $width, int $height): string
    {
        return self::png($width, $height) . '<?php /* ' . self::MARKER . ' */';
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private static function canvas(int $width, int $height): GdImage
    {
        $image = \imagecreatetruecolor($width, $height);
        \imagefill($image, 0, 0, (int) \imagecolorallocate($image, 31, 95, 139));

        return $image;
    }

    /**
     * @param callable(resource): bool $write
     */
    private static function encoded(callable $write): string
    {
        $stream = \fopen('php://memory', 'w+b');
        \assert(false !== $stream, 'A memory stream always opens.');
        $write($stream);
        \rewind($stream);

        return (string) \stream_get_contents($stream);
    }
}
