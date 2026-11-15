<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    #[Test]
    public function it_stores_the_address_trimmed_and_in_lower_case(): void
    {
        $address = EmailAddress::fromString(' Anke@Example.org ');

        $this->assertSame('anke@example.org', $address->value);
    }

    #[Test]
    public function it_keeps_accents_so_an_address_with_and_without_one_stays_distinct(): void
    {
        $address = EmailAddress::fromString('José@Example.org');

        $this->assertSame('josé@example.org', $address->value);
    }

    #[Test]
    public function it_refuses_invalid_utf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailAddress::fromString("jos\xC3\x28@example.org");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'no at sign' => ['example.org'];
        yield 'no local part' => ['@example.org'];
        yield 'no domain' => ['anke@'];
        yield 'space inside' => ['an ke@example.org'];
        yield 'two at signs' => ['anke@home@example.org'];
    }

    #[DataProvider('malformedAddresses')]
    #[Test]
    public function it_refuses_a_malformed_address(string $address): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailAddress::fromString($address);
    }
}
