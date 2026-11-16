<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\LoginCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoginCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function typedCodes(): iterable
    {
        yield 'as sent' => ['482913'];
        yield 'in two groups, as the mail shows it' => ['482 913'];
        yield 'with a hyphen' => ['482-913'];
        yield 'with spaces around it, as pasted' => ["  482 913\n"];
        yield 'with a non-breaking space' => ["482\u{00A0}913"];
    }

    #[DataProvider('typedCodes')]
    #[Test]
    public function it_reads_a_typed_code_without_spaces_and_hyphens(string $typed): void
    {
        $this->assertSame('482913', LoginCode::typed($typed));
    }

    #[Test]
    public function it_shows_a_code_in_two_groups_of_three(): void
    {
        $this->assertSame('482 913', LoginCode::shown('482913'));
    }
}
