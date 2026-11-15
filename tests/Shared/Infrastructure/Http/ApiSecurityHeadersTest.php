<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ApiSecurityHeadersTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function apiResponses(): iterable
    {
        yield 'a successful response' => ['/api/v1/health', 200];
        yield 'an error response' => ['/api/v1/does-not-exist', 404];
    }

    /**
     * In debug mode Symfony drops the policy from an error page so its exception page can load inline styles; without
     * debug the error response keeps it, as in production.
     */
    #[Test]
    #[DataProvider('apiResponses')]
    public function it_sends_the_security_headers_on_every_api_response(string $path, int $status): void
    {
        $client = self::createClient(['debug' => false]);

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertResponseHeaderSame('Cross-Origin-Opener-Policy', 'same-origin');
    }

    /**
     * Pages outside the API, such as the installer, load their own styles and scripts; a policy that allows nothing
     * would leave them blank.
     */
    #[Test]
    public function it_leaves_responses_outside_the_api_without_the_api_policy(): void
    {
        $client = self::createClient();

        $client->request('GET', '/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseNotHasHeader('Content-Security-Policy');
    }
}
