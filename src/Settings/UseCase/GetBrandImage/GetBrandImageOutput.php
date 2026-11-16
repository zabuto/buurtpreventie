<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetBrandImage;

final readonly class GetBrandImageOutput
{
    /**
     * @param string $content a PNG
     */
    public function __construct(
        public string $content,
    ) {
    }
}
