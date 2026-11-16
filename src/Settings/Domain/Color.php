<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * A brand colour as `#rrggbb`, the one notation a CSS custom property, a manifest and a mail all read the same way.
 */
final readonly class Color
{
    private function __construct(
        public string $hex,
    ) {
    }

    /**
     * @throws InvalidColor for anything but a hash and six hexadecimal digits
     */
    public static function fromHex(string $hex): self
    {
        if (1 !== \preg_match('/^#[0-9a-f]{6}$/iD', $hex)) {
            throw new InvalidColor('A colour is a hash and six hexadecimal digits.');
        }

        return new self(\mb_strtolower($hex));
    }

    /**
     * The contrast ratio of WCAG 2.2, from 1 (no contrast) to 21 (black and white), the same in either order.
     */
    public function contrastWith(self $other): float
    {
        $lighter = \max($this->luminance(), $other->luminance());
        $darker = \min($this->luminance(), $other->luminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(): float
    {
        [$red, $green, $blue] = \array_map(
            static fn(string $channel): float => self::linear(\hexdec($channel) / 255),
            \str_split(\substr($this->hex, 1), 2),
        );

        return (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
    }

    private static function linear(float $channel): float
    {
        return $channel <= 0.040_45 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
