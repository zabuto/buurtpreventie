<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * A PSR-18 client that hands each request to the kernel behind a test browser, so the MCP client of the SDK talks to
 * the app with its firewall, switch, scopes and voters, without a server.
 */
final readonly class KernelHttpClient implements ClientInterface
{
    public function __construct(
        private KernelBrowser $browser,
        private Psr17Factory $factory = new Psr17Factory(),
    ) {
    }

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $server = [];
        foreach ($request->getHeaders() as $name => $values) {
            $key = \strtoupper(\str_replace('-', '_', (string) $name));
            $server[\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], strict: true) ? $key : 'HTTP_' . $key] = \implode(', ', $values);
        }

        $this->browser->request($request->getMethod(), (string) $request->getUri(), server: $server, content: (string) $request->getBody());

        $answer = $this->browser->getResponse();
        $response = $this->factory
            ->createResponse($answer->getStatusCode())
            ->withBody($this->factory->createStream((string) $answer->getContent()))
        ;
        foreach ($answer->headers->all() as $name => $values) {
            $response = $response->withHeader((string) $name, \array_map(strval(...), \array_filter($values, \is_string(...))));
        }

        return $response;
    }
}
