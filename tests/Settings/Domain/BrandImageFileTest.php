<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrandImageFileTest extends TestCase
{
    private const string VERSION = '3f2a9c1d0b8e7f65';

    #[Test]
    public function it_names_a_file_after_kind_size_and_version(): void
    {
        $file = BrandImageFile::of(BrandImageKind::Icon, 192, self::VERSION);

        $this->assertSame('icon-192-3f2a9c1d0b8e7f65.png', $file->name());
    }

    #[Test]
    public function it_reads_kind_size_and_version_back_from_a_name(): void
    {
        $file = BrandImageFile::fromName('logo-128-3f2a9c1d0b8e7f65.png');

        $this->assertSame([BrandImageKind::Logo, 128, self::VERSION], [$file?->kind, $file?->size, $file?->version]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignNames(): iterable
    {
        yield 'a path upwards' => ['../../.env.local'];
        yield 'a path inside the name' => ['icon-192-3f2a9c1d0b8e7f65.png/../x'];
        yield 'another kind' => ['banner-512-3f2a9c1d0b8e7f65.png'];
        yield 'a size the kind does not have' => ['logo-192-3f2a9c1d0b8e7f65.png'];
        yield 'a short version' => ['icon-192-3f2a9c1d.png'];
        yield 'capitals in the version' => ['icon-192-3F2A9C1D0B8E7F65.png'];
        yield 'another extension' => ['icon-192-3f2a9c1d0b8e7f65.svg'];
        yield 'a line feed at the end' => ["icon-192-3f2a9c1d0b8e7f65.png\n"];
    }

    #[Test]
    #[DataProvider('foreignNames')]
    public function it_knows_no_name_outside_the_pattern(string $name): void
    {
        $this->assertNull(BrandImageFile::fromName($name));
    }

    #[Test]
    public function it_takes_the_version_from_the_content_so_a_new_image_gets_new_names(): void
    {
        $this->assertSame(
            [16, true, false],
            [
                \strlen(BrandImageFile::versionOf('one')),
                BrandImageFile::versionOf('one') === BrandImageFile::versionOf('one'),
                BrandImageFile::versionOf('one') === BrandImageFile::versionOf('two'),
            ],
        );
    }
}
