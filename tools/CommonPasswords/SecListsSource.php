<?php

declare(strict_types=1);

namespace App\Tools\CommonPasswords;

use RuntimeException;

/**
 * Reads a file of SecLists at one commit, so a rebuild without a new commit gives the same list.
 */
final readonly class SecListsSource
{
    private const string URL = 'https://raw.githubusercontent.com/danielmiessler/SecLists/%s/%s';

    public function __construct(
        private float $timeoutSeconds,
    ) {
    }

    public function file(string $commit, string $path): string
    {
        if (1 !== \preg_match('/^[0-9a-f]{40}$/', $commit)) {
            throw new RuntimeException(\sprintf('"%s" is not a full commit hash of SecLists.', $commit));
        }

        $context = \stream_context_create([
            'http' => [
                'header' => ['User-Agent: buurtpreventie-common-passwords'],
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);
        // Without a network PHP raises a warning; the exception below carries the failure instead.
        \set_error_handler(static fn(): bool => true);
        try {
            $body = \file_get_contents(\sprintf(self::URL, $commit, $path), false, $context);
        } finally {
            \restore_error_handler();
        }

        $status = \http_get_last_response_headers()[0] ?? '';
        if (false === $body || 1 !== \preg_match('#^HTTP/\S+ 200#', $status)) {
            throw new RuntimeException(\sprintf('%s at SecLists commit %s could not be read.', $path, $commit));
        }

        return $body;
    }
}
