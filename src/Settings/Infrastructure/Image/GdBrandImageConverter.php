<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Image;

use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\ImageFacts;
use App\Settings\Domain\Rendition;
use App\Settings\Domain\UnsupportedImage;
use finfo;
use GdImage;

/**
 * Checks an uploaded logo or icon by its content and draws every size the app keeps as a fresh PNG. Only pixels
 * survive the redraw: metadata such as the place a photo was taken, and anything hidden after or inside the image,
 * stay behind. Transparency stays.
 */
final readonly class GdBrandImageConverter
{
    /**
     * @throws UnsupportedImage
     *
     * @return array<int, string> size => PNG
     */
    public function convert(BrandImageKind $kind, string $content): array
    {
        $kind->check($this->facts($content));

        $image = self::quietly(static fn(): GdImage|false => \imagecreatefromstring($content));
        if (!$image instanceof GdImage) {
            throw UnsupportedImage::format();
        }

        $renditions = [];
        foreach ($kind->renditions() as $rendition) {
            $renditions[$rendition->size] = $this->redraw($image, $rendition);
        }

        return $renditions;
    }

    /**
     * Reads only the header of the image, so the limits hold before any pixel is decoded.
     */
    private function facts(string $content): ImageFacts
    {
        $size = self::quietly(static fn(): array|false => \getimagesizefromstring($content));

        return new ImageFacts(
            bytes: \strlen($content),
            imageType: false === $size ? null : $size['mime'],
            fileType: (string) new finfo(\FILEINFO_MIME_TYPE)->buffer($content),
            width: false === $size ? 0 : $size[0],
            height: false === $size ? 0 : $size[1],
        );
    }

    private function redraw(GdImage $image, Rendition $rendition): string
    {
        [$width, $height] = $rendition->fit(\imagesx($image), \imagesy($image));
        $canvas = \imagecreatetruecolor($width, $height);
        \imagealphablending($canvas, false);
        \imagesavealpha($canvas, true);
        \imagefill($canvas, 0, 0, (int) \imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        \imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, \imagesx($image), \imagesy($image));

        $stream = \fopen('php://memory', 'w+b');
        \assert(false !== $stream, 'A memory stream always opens.');
        \imagepng($canvas, $stream);
        \rewind($stream);

        return (string) \stream_get_contents($stream);
    }

    /**
     * GD and getimagesize() warn about content they cannot read and then return false; the false is the answer, and a
     * warning would turn into an error page.
     *
     * @template T
     *
     * @param callable(): T $read
     *
     * @return T
     */
    private static function quietly(callable $read): mixed
    {
        \set_error_handler(static fn(): bool => true);
        try {
            return $read();
        } finally {
            \restore_error_handler();
        }
    }
}
