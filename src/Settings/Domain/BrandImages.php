<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * The stored sizes of the uploaded logo and icon, outside the web folder.
 */
interface BrandImages
{
    /**
     * Checks the upload and stores every size of its kind at once, before any setting names them.
     *
     * @throws UnsupportedImage
     *
     * @return string the version in the names of the stored files
     */
    public function store(BrandImageKind $kind, string $content): string;

    /**
     * @return ?string the PNG, or null when no such file is stored
     */
    public function content(BrandImageFile $file): ?string;

    /**
     * Removes every size of one version; a size that is not there counts as removed.
     */
    public function remove(BrandImageKind $kind, string $version): void;
}
