<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * The four brand colours, each checked against the background of its mode as the frontend paints it: white in light
 * mode, #121212 in dark mode. One colour cannot serve both: 4.5:1 against white needs a relative luminance of at most
 * 0.18, against #121212 at least 0.20.
 *
 * The primary colour carries text, links and buttons with text in the background colour, so it needs 4.5:1; the
 * accent marks borders and focus, which WCAG 1.4.11 sets at 3:1.
 */
final readonly class BrandPalette
{
    public const string LIGHT_BACKGROUND = '#ffffff';
    public const string DARK_BACKGROUND = '#121212';
    private const float TEXT = 4.5;
    private const float GRAPHICS = 3.0;

    /**
     * @throws InsufficientContrast for the first colour without enough contrast against its background
     */
    public function __construct(
        public Color $primary,
        public Color $accent,
        public Color $primaryDark,
        public Color $accentDark,
    ) {
        $light = Color::fromHex(self::LIGHT_BACKGROUND);
        $dark = Color::fromHex(self::DARK_BACKGROUND);

        self::require(SettingName::PrimaryColor, $primary, $light, self::TEXT);
        self::require(SettingName::AccentColor, $accent, $light, self::GRAPHICS);
        self::require(SettingName::PrimaryDarkColor, $primaryDark, $dark, self::TEXT);
        self::require(SettingName::AccentDarkColor, $accentDark, $dark, self::GRAPHICS);
    }

    public static function default(): self
    {
        return new self(Color::fromHex('#1f5f8b'), Color::fromHex('#c2410c'), Color::fromHex('#8cc4e8'), Color::fromHex('#fb923c'));
    }

    private static function require(SettingName $setting, Color $color, Color $background, float $required): void
    {
        $ratio = $color->contrastWith($background);
        if ($ratio < $required) {
            throw new InsufficientContrast($setting, $ratio, $required);
        }
    }
}
