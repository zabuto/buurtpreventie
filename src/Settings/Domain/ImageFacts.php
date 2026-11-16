<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * What an uploaded file is, read from its content before it is decoded: the type its image header names, the type
 * its bytes show, and the pixels the header promises. Name, extension and the type a client sends count for nothing.
 */
final readonly class ImageFacts
{
    /**
     * @param ?string $imageType the media type of the image header, null when there is none
     * @param string  $fileType  the media type the content shows by its magic bytes
     */
    public function __construct(
        public int $bytes,
        public ?string $imageType,
        public string $fileType,
        public int $width,
        public int $height,
    ) {
    }
}
