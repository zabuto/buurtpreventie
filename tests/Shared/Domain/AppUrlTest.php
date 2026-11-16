<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\AppUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AppUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function baseUrls(): iterable
    {
        yield 'subdomain' => ['https://buurt.example.org', 'https://buurt.example.org/app/#/rounds/r-1'];
        yield 'subdomain with a trailing slash' => ['https://buurt.example.org/', 'https://buurt.example.org/app/#/rounds/r-1'];
        yield 'path' => ['https://example.org/buurtpreventie', 'https://example.org/buurtpreventie/app/#/rounds/r-1'];
        yield 'path with a trailing slash' => ['https://example.org/buurtpreventie/', 'https://example.org/buurtpreventie/app/#/rounds/r-1'];
        yield 'port and nested path' => ['http://localhost:8000/a/b//', 'http://localhost:8000/a/b/app/#/rounds/r-1'];
    }

    #[Test]
    #[DataProvider('baseUrls')]
    public function it_puts_a_link_under_the_path_of_the_base_url_with_one_slash_between(string $base, string $link): void
    {
        $url = AppUrl::fromString($base);

        $this->assertSame($link, $url->to('app/#/rounds/r-1'));
    }

    #[Test]
    public function it_gives_the_same_link_whether_or_not_the_path_starts_with_a_slash(): void
    {
        $url = AppUrl::fromString('https://example.org/buurtpreventie');

        $this->assertSame($url->to('app/'), $url->to('/app/'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedBaseUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'no scheme' => ['buurt.example.org'];
        yield 'another scheme' => ['ftp://buurt.example.org'];
        yield 'no host' => ['https:///buurtpreventie'];
        yield 'a query' => ['https://buurt.example.org/?tenant=1'];
        yield 'a fragment' => ['https://buurt.example.org/#/app'];
    }

    /**
     * A query or fragment in the base would end up in the middle of every link.
     */
    #[Test]
    #[DataProvider('malformedBaseUrls')]
    public function it_refuses_a_base_that_is_not_an_http_address_with_a_host_and_at_most_a_path(string $base): void
    {
        $this->expectException(InvalidArgumentException::class);

        AppUrl::fromString($base);
    }
}
