<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * The requests of the Hurl files that go to the API, as METHOD path with the Hurl variables left in. A request starts
 * a line with its method; the query string, the description itself, other hosts such as Mailpit, and a path that
 * comes from a variable are no part of it.
 */
final readonly class HurlRequests
{
    private const string REQUEST = '#^(GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS) \{\{baseUrl\}\}(/[^\s?]*)#m';
    private const string DESCRIPTION = '/api/v1/docs.json';

    /**
     * @return list<string>
     */
    public static function fromDirectory(string $directory): array
    {
        $files = \glob($directory . '/*.hurl');
        $files = false === $files ? [] : $files;
        \sort($files);

        $requests = [];
        foreach ($files as $file) {
            $requests = [...$requests, ...self::fromText((string) \file_get_contents($file))];
        }

        return $requests;
    }

    /**
     * @return list<string>
     */
    public static function fromText(string $text): array
    {
        \preg_match_all(self::REQUEST, $text, $matches, \PREG_SET_ORDER);

        $requests = [];
        foreach ($matches as [, $method, $path]) {
            if (self::DESCRIPTION !== $path) {
                $requests[] = $method . ' ' . $path;
            }
        }

        return $requests;
    }
}
