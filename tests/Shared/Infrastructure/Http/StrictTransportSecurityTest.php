<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ApiSecurityHeaders;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * HSTS is opt-in per installation through HSTS_MAX_AGE: once a browser has it, an installation that loses its
 * certificate locks its visitors out until the max-age ends.
 */
final class StrictTransportSecurityTest extends TestCase
{
    #[Test]
    public function it_tells_the_browser_to_keep_to_https_when_the_installation_opts_in(): void
    {
        $response = self::respond(new ApiSecurityHeaders(hstsMaxAge: 31_536_000), 'https://buurtpreventie.example.org/api/v1/health');

        $this->assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function it_sends_no_hsts_when_the_installation_does_not_opt_in(): void
    {
        $response = self::respond(new ApiSecurityHeaders(hstsMaxAge: 0), 'https://buurtpreventie.example.org/api/v1/health');

        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    /**
     * A browser ignores the header over plain HTTP, so sending it there would only suggest a protection that is not on.
     */
    #[Test]
    public function it_sends_no_hsts_over_plain_http(): void
    {
        $response = self::respond(new ApiSecurityHeaders(hstsMaxAge: 31_536_000), 'http://buurtpreventie.example.org/api/v1/health');

        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    private static function respond(ApiSecurityHeaders $headers, string $uri): Response
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, Request::create($uri), HttpKernelInterface::MAIN_REQUEST, new Response());

        $headers($event);

        return $event->getResponse();
    }
}
