<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\Color;
use App\Settings\Domain\InvalidColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contrast ratios as WCAG 2.2 defines them; the expected values come from the WCAG formula, checked by hand.
 */
final class ColorTest extends TestCase
{
    #[Test]
    public function it_keeps_a_colour_in_lower_case(): void
    {
        $this->assertSame('#1f5f8b', Color::fromHex('#1F5F8B')->hex);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'without hash' => ['1f5f8b'];
        yield 'three digits' => ['#fff'];
        yield 'with alpha' => ['#1f5f8bff'];
        yield 'not hexadecimal' => ['#1g5f8b'];
        yield 'a name' => ['red'];
        yield 'trailing newline' => ["#1f5f8b\n"];
    }

    #[Test]
    #[DataProvider('malformed')]
    public function it_refuses_anything_but_six_hexadecimal_digits_after_a_hash(string $hex): void
    {
        $this->expectException(InvalidColor::class);

        Color::fromHex($hex);
    }

    /**
     * @return iterable<string, array{string, string, float}>
     */
    public static function pairs(): iterable
    {
        yield 'black on white' => ['#000000', '#ffffff', 21.0];
        yield 'white on white' => ['#ffffff', '#ffffff', 1.0];
        yield 'the lightest grey that passes on white' => ['#767676', '#ffffff', 4.54];
        yield 'one step lighter fails on white' => ['#777777', '#ffffff', 4.48];
        yield 'order does not matter' => ['#ffffff', '#767676', 4.54];
        yield 'light blue on near-black' => ['#8cc4e8', '#121212', 9.97];
    }

    #[Test]
    #[DataProvider('pairs')]
    public function it_measures_the_contrast_ratio_of_two_colours(string $foreground, string $background, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, Color::fromHex($foreground)->contrastWith(Color::fromHex($background)), 0.01);
    }
}
