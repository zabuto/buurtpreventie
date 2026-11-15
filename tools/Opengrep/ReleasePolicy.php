<?php

declare(strict_types=1);

namespace App\Tools\Opengrep;

use DateTimeImmutable;

/**
 * The same cooldown as Dependabot gives Composer packages: a hijacked release is usually withdrawn within days. A new
 * major never arrives automatically, because it may change rule syntax or results; it is only reported.
 */
final readonly class ReleasePolicy
{
    private const int PATCH_DAYS = 7;
    private const int MINOR_DAYS = 7;
    private const int MAJOR_DAYS = 14;

    /**
     * @param list<Release> $releases
     *
     * @throws NoEligibleRelease
     */
    public function pick(array $releases, int $major, DateTimeImmutable $now): Release
    {
        $eligible = \array_filter(
            $releases,
            fn(Release $release): bool => (
                $release->major === $major
                && $this->hasWaited($release, 0 === $release->patch ? self::MINOR_DAYS : self::PATCH_DAYS, $now)
            ),
        );

        return $this->newest($eligible) ?? throw new NoEligibleRelease(\sprintf('No Opengrep %d.x release has passed its cooldown.', $major));
    }

    /**
     * @param list<Release> $releases
     */
    public function newerMajor(array $releases, int $major, DateTimeImmutable $now): ?Release
    {
        return $this->newest(\array_filter(
            $releases,
            fn(Release $release): bool => $release->major > $major && $this->hasWaited($release, self::MAJOR_DAYS, $now),
        ));
    }

    private function hasWaited(Release $release, int $days, DateTimeImmutable $now): bool
    {
        return $release->publishedAt->modify(\sprintf('+%d days', $days)) <= $now;
    }

    /**
     * @param array<Release> $releases
     */
    private function newest(array $releases): ?Release
    {
        \usort($releases, static fn(Release $a, Release $b): int => [$b->major, $b->minor, $b->patch] <=> [$a->major, $a->minor, $a->patch]);

        return $releases[0] ?? null;
    }
}
