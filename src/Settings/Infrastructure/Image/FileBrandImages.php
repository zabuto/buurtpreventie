<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Image;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\BrandImages;
use App\Settings\Domain\Rendition;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

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

        $filesystem = new Filesystem();
        foreach ($renditions as $size => $png) {
            $filesystem->dumpFile($this->path(BrandImageFile::of($kind, $size, $version)), $png);
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
        new Filesystem()->remove(\array_map(
            fn(Rendition $rendition): string => $this->path(BrandImageFile::of($kind, $rendition->size, $version)),
            $kind->renditions(),
        ));
    }

    private function path(BrandImageFile $file): string
    {
        return $this->directory . '/brand/' . $file->name();
    }
}
