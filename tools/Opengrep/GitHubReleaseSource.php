<?php

declare(strict_types=1);

namespace App\Tools\Opengrep;

use RuntimeException;

/**
 * Reads the newest 100 releases of opengrep/opengrep, which reach back months. In CI the GITHUB_TOKEN raises the
 * rate limit; elsewhere one anonymous request per run stays far below it.
 */
final readonly class GitHubReleaseSource implements ReleaseSource
{
    private const string URL = 'https://api.github.com/repos/opengrep/opengrep/releases?per_page=100';

    public function __construct(
        private float $timeoutSeconds,
        #[\SensitiveParameter]
        private ?string $token,
    ) {
    }

    public function releases(): array
    {
        $headers = ['Accept: application/vnd.github+json', 'User-Agent: buurtpreventie-opengrep-release'];
        if (null !== $this->token && '' !== $this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $context = \stream_context_create(['http' => [
            'header' => $headers,
            'timeout' => $this->timeoutSeconds,
            'ignore_errors' => true,
        ]]);
        // Without a network PHP raises a warning; the exception below carries the failure instead.
        \set_error_handler(static fn(): bool => true);
        try {
            $body = \file_get_contents(self::URL, false, $context);
        } finally {
            \restore_error_handler();
        }

        $status = \http_get_last_response_headers()[0] ?? '';
        if (false === $body || 1 !== \preg_match('#^HTTP/\S+ 200#', $status)) {
            throw new RuntimeException('The GitHub API could not be reached.');
        }

        return GitHubReleaseList::parse($body);
    }
}
