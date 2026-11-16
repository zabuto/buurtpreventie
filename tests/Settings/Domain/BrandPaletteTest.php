<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\BrandPalette;
use App\Settings\Domain\Color;
use App\Settings\Domain\InsufficientContrast;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The thresholds are the greys on either side of each rule, worked out by hand with the WCAG formula: the lighter or
 * darker neighbour of each passing grey fails.
 */
final class BrandPaletteTest extends TestCase
{
    private const string PRIMARY = '#1f5f8b';
    private const string ACCENT = '#c2410c';
    private const string PRIMARY_DARK = '#8cc4e8';
    private const string ACCENT_DARK = '#fb923c';

    #[Test]
    public function it_accepts_the_default_palette(): void
    {
        $palette = BrandPalette::default();

        $this->assertSame(
            [self::PRIMARY, self::ACCENT, self::PRIMARY_DARK, self::ACCENT_DARK],
            [$palette->primary->hex, $palette->accent->hex, $palette->primaryDark->hex, $palette->accentDark->hex],
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function lastAllowed(): iterable
    {
        yield 'primary: 4.5 against white' => ['primary', '#767676'];
        yield 'accent: 3 against white' => ['accent', '#949494'];
        yield 'dark primary: 4.5 against #121212' => ['primaryDark', '#7d7d7d'];
        yield 'dark accent: 3 against #121212' => ['accentDark', '#616161'];
    }

    #[Test]
    #[DataProvider('lastAllowed')]
    public function it_accepts_a_colour_with_just_enough_contrast(string $role, string $hex): void
    {
        $palette = self::paletteWith($role, $hex);

        $color = match ($role) {
            'primary' => $palette->primary,
            'accent' => $palette->accent,
            'primaryDark' => $palette->primaryDark,
            default => $palette->accentDark,
        };

        $this->assertSame($hex, $color->hex);
    }

    /**
     * @return iterable<string, array{string, string, array{setting: string, ratio: float, required: float}}>
     */
    public static function tooWeak(): iterable
    {
        yield 'primary' => ['primary', '#777777', ['setting' => 'brand.primary', 'ratio' => 4.47, 'required' => 4.5]];
        yield 'accent' => ['accent', '#959595', ['setting' => 'brand.accent', 'ratio' => 2.99, 'required' => 3.0]];
        yield 'dark primary' => ['primaryDark', '#7c7c7c', ['setting' => 'brand.primary_dark', 'ratio' => 4.48, 'required' => 4.5]];
        yield 'dark accent' => ['accentDark', '#606060', ['setting' => 'brand.accent_dark', 'ratio' => 2.97, 'required' => 3.0]];
    }

    /**
     * @param array{setting: string, ratio: float, required: float} $details
     */
    #[Test]
    #[DataProvider('tooWeak')]
    public function it_refuses_a_colour_without_enough_contrast_and_says_how_much_it_has(string $role, string $hex, array $details): void
    {
        try {
            self::paletteWith($role, $hex);
            $this->fail('A colour without enough contrast passed.');
        } catch (InsufficientContrast $refusal) {
            $this->assertSame($details, $refusal->details());
        }
    }

    private static function paletteWith(string $role, string $hex): BrandPalette
    {
        $colors = ['primary' => self::PRIMARY, 'accent' => self::ACCENT, 'primaryDark' => self::PRIMARY_DARK, 'accentDark' => self::ACCENT_DARK];
        $colors[$role] = $hex;

        return new BrandPalette(
            Color::fromHex($colors['primary']),
            Color::fromHex($colors['accent']),
            Color::fromHex($colors['primaryDark']),
            Color::fromHex($colors['accentDark']),
        );
    }
}
