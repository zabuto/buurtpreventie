<?php

declare(strict_types=1);

namespace App\Tests\Settings\Infrastructure\Image;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\UnsupportedImage;
use App\Settings\Infrastructure\Image\FileBrandImages;
use App\Settings\Infrastructure\Image\GdBrandImageConverter;
use App\Tests\Settings\Fake\TestImages;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class FileBrandImagesTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/brand-images-' . \bin2hex(\random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    #[Test]
    public function it_stores_every_size_under_brand_named_after_the_version_of_the_upload(): void
    {
        $upload = TestImages::png(1024, 1024);

        $version = $this->images()->store(BrandImageKind::Icon, $upload);

        $this->assertSame(
            [
                BrandImageFile::versionOf($upload),
                ['icon-180-' . $version . '.png', 'icon-192-' . $version . '.png', 'icon-32-' . $version . '.png', 'icon-512-' . $version . '.png'],
            ],
            [$version, $this->files()],
        );
    }

    #[Test]
    public function it_gives_the_stored_png_of_one_size(): void
    {
        $version = $this->images()->store(BrandImageKind::Logo, TestImages::png(1000, 250));

        $png = $this->images()->content(BrandImageFile::of(BrandImageKind::Logo, 128, $version));

        $size = \getimagesizefromstring((string) $png);
        $this->assertSame([512, 128], false === $size ? null : [$size[0], $size[1]]);
    }

    #[Test]
    public function it_gives_nothing_for_a_file_it_does_not_hold(): void
    {
        $this->assertNull($this->images()->content(BrandImageFile::of(BrandImageKind::Logo, 512, '0123456789abcdef')));
    }

    #[Test]
    public function it_stores_nothing_of_a_refused_upload(): void
    {
        $refusal = null;
        try {
            $this->images()->store(BrandImageKind::Icon, TestImages::png(600, 500));
        } catch (UnsupportedImage $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(UnsupportedImage::class, $refusal);
        $this->assertSame([], $this->files());
    }

    #[Test]
    public function it_removes_every_size_of_one_version_and_leaves_the_others(): void
    {
        $old = $this->images()->store(BrandImageKind::Logo, TestImages::png(300, 100));
        $new = $this->images()->store(BrandImageKind::Logo, TestImages::png(301, 100));

        $this->images()->remove(BrandImageKind::Logo, $old);

        $this->assertSame(['logo-128-' . $new . '.png', 'logo-512-' . $new . '.png'], $this->files());
    }

    private function images(): FileBrandImages
    {
        return new FileBrandImages(new GdBrandImageConverter(), $this->directory);
    }

    /**
     * @return list<string> the file names under brand/, in order
     */
    private function files(): array
    {
        $files = \array_map(\basename(...), $this->paths());
        \sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        $paths = \glob($this->directory . '/brand/*');

        return false === $paths ? [] : $paths;
    }
}
