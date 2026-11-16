<?php

declare(strict_types=1);

namespace App\Tests\Settings\Fake;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\BrandImages;
use App\Settings\Infrastructure\Image\GdBrandImageConverter;
use Override;

/**
 * Keeps the files in memory and checks an upload with the real converter, so a refused upload is refused here too.
 */
final class InMemoryBrandImages implements BrandImages
{
    /** @var array<string, string> file name => PNG */
    public array $files = [];

    #[Override]
    public function store(BrandImageKind $kind, string $content): string
    {
        $version = BrandImageFile::versionOf($content);
        foreach (new GdBrandImageConverter()->convert($kind, $content) as $size => $png) {
            $this->files[BrandImageFile::of($kind, $size, $version)->name()] = $png;
        }

        return $version;
    }

    #[Override]
    public function content(BrandImageFile $file): ?string
    {
        return $this->files[$file->name()] ?? null;
    }

    #[Override]
    public function remove(BrandImageKind $kind, string $version): void
    {
        foreach ($kind->renditions() as $rendition) {
            unset($this->files[BrandImageFile::of($kind, $rendition->size, $version)->name()]);
        }
    }

    /**
     * @return list<string> the stored file names, in order
     */
    public function names(): array
    {
        $names = \array_keys($this->files);
        \sort($names);

        return $names;
    }
}
