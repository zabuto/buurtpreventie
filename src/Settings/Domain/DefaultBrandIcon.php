<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * The neutral icon of the app, for an association that uploaded none.
 */
interface DefaultBrandIcon
{
    /**
     * @param int $size one of the sizes of an icon rendition
     *
     * @return string a PNG, the same on every call
     */
    public function png(int $size): string;
}
