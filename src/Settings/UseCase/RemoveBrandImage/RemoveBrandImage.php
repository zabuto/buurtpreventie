<?php

declare(strict_types=1);

namespace App\Settings\UseCase\RemoveBrandImage;

use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\InvalidSetting;

final readonly class RemoveBrandImage
{
    /**
     * @param string $kind `logo` or `icon`
     */
    public function __construct(
        public string $kind,
    ) {
    }

    /**
     * @throws InvalidSetting for a kind of image the app does not have
     */
    public function kind(): BrandImageKind
    {
        return BrandImageKind::tryFrom($this->kind) ?? throw new InvalidSetting($this->kind, \sprintf('There is no brand image "%s".', $this->kind));
    }
}
