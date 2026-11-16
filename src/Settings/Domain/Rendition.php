<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * One size the app keeps of a logo or icon: a box the image is fitted in, named by its size in the file name.
 */
final readonly class Rendition
{
    public function __construct(
        public int $size,
        public int $maxWidth,
        public int $maxHeight,
    ) {
    }

    /**
     * Never enlarges, as that only blurs, and keeps at least one pixel per side.
     *
     * @return array{positive-int, positive-int} width and height
     */
    public function fit(int $width, int $height): array
    {
        $scale = \min(1.0, $this->maxWidth / $width, $this->maxHeight / $height);

        return [\max(1, (int) \round($width * $scale)), \max(1, (int) \round($height * $scale))];
    }
}
