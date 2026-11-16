<?php

declare(strict_types=1);

namespace App\Settings\UseCase\ChangeBrandImage;

use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\InvalidSetting;

/**
 * A new logo or icon as the bytes of the file. What the file is follows from those bytes alone.
 */
final readonly class ChangeBrandImage
{
    /** One byte over the limit, so a larger upload is refused without reading the rest of it. */
    public const int READ_AT_MOST = (2 * 1024 * 1024) + 1;

    /**
     * @param string $kind    `logo` or `icon`
     * @param string $content the file, unread beyond READ_AT_MOST bytes
     */
    public function __construct(
        public string $kind,
        public string $content,
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
