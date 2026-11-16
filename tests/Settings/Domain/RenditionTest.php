<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\Rendition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RenditionTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, array{int, int}}>
     */
    public static function images(): iterable
    {
        yield 'a square twice the box' => [1024, 1024, [512, 512]];
        yield 'a wide image, held by its width' => [2048, 300, [512, 75]];
        yield 'a tall image, held by its height' => [300, 1200, [128, 512]];
        yield 'an image smaller than the box' => [200, 100, [200, 100]];
        yield 'a sliver that would round to nothing' => [2048, 1, [512, 1]];
    }

    /**
     * @param array{int, int} $expected
     */
    #[Test]
    #[DataProvider('images')]
    public function it_fits_an_image_in_its_box_keeping_the_proportions_and_never_enlarging(int $width, int $height, array $expected): void
    {
        $rendition = new Rendition(512, 512, 512);

        $this->assertSame($expected, $rendition->fit($width, $height));
    }

    #[Test]
    public function it_holds_a_wide_logo_for_mail_to_128_pixels_high_and_512_wide(): void
    {
        $rendition = new Rendition(128, 512, 128);

        $this->assertSame([[512, 64], [384, 128]], [$rendition->fit(2048, 256), $rendition->fit(600, 200)]);
    }
}
