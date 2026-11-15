<?php

declare(strict_types=1);

namespace App\Tools\Opengrep;

use DateTimeImmutable;
use UnexpectedValueException;

final readonly class GitHubReleaseList
{
    /**
     * Drafts and prereleases are skipped, as are tags such as `v2.0.0-nopython.alpha.1`, which are no release line.
     *
     * @return list<Release>
     *
     * @throws UnexpectedValueException
     */
    public static function parse(string $json): array
    {
        $list = \json_decode($json, true);
        if (!\is_array($list) || !\array_is_list($list)) {
            throw new UnexpectedValueException('The GitHub API returned no release list.');
        }

        $releases = [];
        foreach ($list as $item) {
            if (!\is_array($item) || true === ($item['draft'] ?? true) || true === ($item['prerelease'] ?? true)) {
                continue;
            }

            $tag = $item['tag_name'] ?? null;
            $publishedAt = $item['published_at'] ?? null;
            if (!\is_string($tag) || !\is_string($publishedAt) || 1 !== \preg_match('/^v(\d+)\.(\d+)\.(\d+)$/', $tag, $version)) {
                continue;
            }

            $releases[] = new Release((int) $version[1], (int) $version[2], (int) $version[3], new DateTimeImmutable($publishedAt));
        }

        return $releases;
    }
}
