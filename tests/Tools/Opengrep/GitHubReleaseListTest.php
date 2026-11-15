<?php

declare(strict_types=1);

namespace App\Tests\Tools\Opengrep;

use App\Tools\Opengrep\GitHubReleaseList;
use App\Tools\Opengrep\Release;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[Group('integration')]
final class GitHubReleaseListTest extends TestCase
{
    /**
     * The fixture is a response of the GitHub releases API for opengrep/opengrep, trimmed to five releases, without
     * assets, body and author; the draft is a copy of a real release.
     */
    #[Test]
    public function it_keeps_only_published_releases_with_a_plain_version_tag(): void
    {
        $json = (string) \file_get_contents(__DIR__ . '/Fixtures/releases.json');

        $releases = GitHubReleaseList::parse($json);

        $this->assertEquals(
            [
                new Release(2, 0, 0, new DateTimeImmutable('2026-11-13T13:14:27Z')),
                new Release(1, 33, 1, new DateTimeImmutable('2026-10-29T10:22:46Z')),
                new Release(1, 33, 0, new DateTimeImmutable('2026-10-27T12:58:44Z')),
            ],
            $releases,
        );
    }

    #[Test]
    public function it_refuses_a_response_that_is_not_a_release_list(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException('The GitHub API returned no release list.'));

        GitHubReleaseList::parse('{"message": "API rate limit exceeded"}');
    }
}
