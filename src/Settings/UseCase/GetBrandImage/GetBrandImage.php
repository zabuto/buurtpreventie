<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetBrandImage;

final readonly class GetBrandImage
{
    /**
     * @param string $file a file name such as `icon-192-3f2a9c1d0b8e7f65.png`, as the request gives it
     */
    public function __construct(
        public string $file,
    ) {
    }
}
