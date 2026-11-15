<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\LookupKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LookupKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function spellingsOfJose(): iterable
    {
        yield 'accent' => ['José'];
        yield 'no accent' => ['Jose'];
        yield 'upper case' => ['JOSE'];
        yield 'upper case with accent' => ['JOSÉ'];
    }

    #[DataProvider('spellingsOfJose')]
    #[Test]
    public function it_ignores_case_and_accents(string $name): void
    {
        $key = LookupKey::fromName($name);

        $this->assertSame('jose', $key->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function spacedNames(): iterable
    {
        yield 'double space' => ['Jan  H.'];
        yield 'leading and trailing spaces' => ['  Jan H. '];
        yield 'tab' => ["Jan\tH."];
    }

    #[DataProvider('spacedNames')]
    #[Test]
    public function it_counts_a_run_of_whitespace_as_one_space(string $name): void
    {
        $key = LookupKey::fromName($name);

        $this->assertSame('jan h.', $key->value);
    }

    #[Test]
    public function it_keeps_a_letter_that_does_not_decompose_into_base_and_accent(): void
    {
        $key = LookupKey::fromName('Søren');

        $this->assertSame('søren', $key->value);
    }

    #[Test]
    public function it_refuses_invalid_utf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LookupKey::fromName("Jos\xC3\x28");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankNames(): iterable
    {
        yield 'empty' => [''];
        yield 'only whitespace' => [" \t "];
    }

    #[DataProvider('blankNames')]
    #[Test]
    public function it_refuses_a_name_without_letters_to_key(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        LookupKey::fromName($name);
    }
}
