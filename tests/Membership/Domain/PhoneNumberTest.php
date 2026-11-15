<?php

declare(strict_types=1);

namespace App\Tests\Membership\Domain;

use App\Membership\Domain\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    #[Test]
    public function it_trims_and_collapses_spaces(): void
    {
        $phone = PhoneNumber::fromString('  06  1234 5678 ');

        $this->assertSame('06 1234 5678', $phone->value);
    }

    #[Test]
    public function it_accepts_an_international_number(): void
    {
        $phone = PhoneNumber::fromString('+31 (0)6-12345678');

        $this->assertSame('+31 (0)6-12345678', $phone->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'letters' => ['06 1234 abcd'];
        yield 'too few digits' => ['12345'];
        yield 'plus sign inside' => ['06+12345678'];
        yield 'empty' => ['  '];
    }

    #[DataProvider('invalidNumbers')]
    #[Test]
    public function it_refuses_what_is_not_a_phone_number(string $number): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhoneNumber::fromString($number);
    }
}
