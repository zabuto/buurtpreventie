<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

/**
 * The base URL of the app, on a subdomain or under a path, and the one source of every absolute link the app sends
 * outside a request, such as in a mail. Built from `DEFAULT_URI`, so a link carries the path of the installation
 * and never a folder the web server happens to serve from.
 */
final readonly class AppUrl
{
    private function __construct(
        private string $base,
    ) {
    }

    /**
     * @throws InvalidArgumentException for anything but an http or https address with a host and at most a path,
     *                                  since a query or fragment would land in the middle of every link
     */
    public static function fromString(string $url): self
    {
        $parts = \parse_url($url);
        if (
            false === $parts
            || !\in_array($parts['scheme'] ?? null, ['http', 'https'], strict: true)
            || null === ($parts['host'] ?? null)
            || null !== ($parts['query'] ?? null)
            || null !== ($parts['fragment'] ?? null)
        ) {
            throw new InvalidArgumentException('The base URL of the app must be an http or https address with a host and at most a path.');
        }

        return new self(\rtrim($url, '/'));
    }

    /**
     * The absolute address of a path inside the app, such as `app/#/rounds/<id>`.
     */
    public function to(string $path): string
    {
        return $this->base . '/' . \ltrim($path, '/');
    }
}
