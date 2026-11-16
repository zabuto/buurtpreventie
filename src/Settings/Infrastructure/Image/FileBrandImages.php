<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Image;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\BrandImages;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps the images in `brand/` under `UPLOAD_DIR`, outside the web folder, so the web server never serves an upload
 * as it is. Each file is written under a temporary name and then renamed, so a reader never gets half a file.
 */
final readonly class FileBrandImages implements BrandImages
{
    public function __construct(
        private GdBrandImageConverter $converter,
        #[Autowire(env: 'UPLOAD_DIR')]
        private string $directory,
    ) {
    }

    #[Override]
    public function store(BrandImageKind $kind, string $content): string
    {
        $renditions = $this->converter->convert($kind, $content);
        $version = BrandImageFile::versionOf($content);

        foreach ($renditions as $size => $png) {
            $this->write(BrandImageFile::of($kind, $size, $version), $png);
        }

        return $version;
    }

    #[Override]
    public function content(BrandImageFile $file): ?string
    {
        $path = $this->path($file);

        return \is_file($path) ? (string) \file_get_contents($path) : null;
    }

    #[Override]
    public function remove(BrandImageKind $kind, string $version): void
    {
        foreach ($kind->renditions() as $rendition) {
            $path = $this->path(BrandImageFile::of($kind, $rendition->size, $version));
            if (\is_file($path)) {
                \unlink($path);
            }
        }
    }

    /**
     * @throws RuntimeException when the folder cannot be made or written to
     */
    private function write(BrandImageFile $file, string $png): void
    {
        $folder = $this->directory . '/brand';
        if (!\is_dir($folder) && !\mkdir($folder, 0o775, recursive: true) && !\is_dir($folder)) {
            throw new RuntimeException(\sprintf('Cannot create the folder "%s".', $folder));
        }

        $temporary = \tempnam($folder, '.upload-');
        if (false === $temporary || false === \file_put_contents($temporary, $png) || !\rename($temporary, $this->path($file))) {
            throw new RuntimeException(\sprintf('Cannot write "%s" in "%s".', $file->name(), $folder));
        }
    }

    private function path(BrandImageFile $file): string
    {
        return $this->directory . '/brand/' . $file->name();
    }
}
