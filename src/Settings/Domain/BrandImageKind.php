<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\BackedEnumValues;

/**
 * The two images an association uploads: a logo of any proportion, and a square app icon that also gives the
 * favicon. The limits hold before an image is decoded, since decoding puts every pixel in memory: 2048² is 16 MB.
 */
enum BrandImageKind: string
{
    use BackedEnumValues;

    case Logo = 'logo';
    case Icon = 'icon';

    private const int MOST_BYTES = 2 * 1024 * 1024;
    private const int MOST_PIXELS = 2048;
    private const int LEAST_ICON_PIXELS = 512;

    /** Raise it when the drawing of the default icon changes, so browsers that cached the old one fetch it anew. */
    private const string DEFAULT_ICON_VERSION = 'defa017000000001';

    /** GIF animates and adds nothing over PNG; WebP and AVIF depend on how GD was built on a host. */
    private const array TYPES = ['image/png', 'image/jpeg'];

    public function setting(): SettingName
    {
        return match ($this) {
            self::Logo => SettingName::Logo,
            self::Icon => SettingName::Icon,
        };
    }

    /**
     * The version under which the app serves its own image when none is uploaded: an icon, as a web app needs one to
     * install. Without a logo the name of the association stands in its place.
     */
    public function defaultVersion(): ?string
    {
        return match ($this) {
            self::Logo => null,
            self::Icon => self::DEFAULT_ICON_VERSION,
        };
    }

    /**
     * @return list<Rendition>
     */
    public function renditions(): array
    {
        return match ($this) {
            self::Logo => [new Rendition(512, 512, 512), new Rendition(128, 512, 128)],
            self::Icon => [new Rendition(512, 512, 512), new Rendition(192, 192, 192), new Rendition(180, 180, 180), new Rendition(32, 32, 32)],
        };
    }

    /**
     * @throws UnsupportedImage
     */
    public function check(ImageFacts $image): void
    {
        if ($image->bytes > self::MOST_BYTES) {
            throw UnsupportedImage::fileSize();
        }

        if (!\in_array($image->imageType, self::TYPES, strict: true) || $image->imageType !== $image->fileType) {
            throw UnsupportedImage::format();
        }

        if ($image->width > self::MOST_PIXELS || $image->height > self::MOST_PIXELS) {
            throw UnsupportedImage::tooLarge();
        }

        if (self::Logo === $this) {
            return;
        }

        if ($image->width !== $image->height) {
            throw UnsupportedImage::notSquare();
        }

        if ($image->width < self::LEAST_ICON_PIXELS) {
            throw UnsupportedImage::tooSmall();
        }
    }
}
