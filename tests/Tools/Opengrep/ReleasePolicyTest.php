<?php

declare(strict_types=1);

namespace App\Tests\Tools\Opengrep;

use App\Tools\Opengrep\NoEligibleRelease;
use App\Tools\Opengrep\Release;
use App\Tools\Opengrep\ReleasePolicy;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReleasePolicyTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00Z';

    #[Test]
    public function it_picks_the_newest_release_of_the_major_that_has_passed_its_cooldown(): void
    {
        $releases = [
            $this->release(1, 32, 0, '2026-10-22T10:00:00Z'),
            $this->release(1, 33, 0, '2026-10-27T10:00:00Z'),
            $this->release(1, 33, 1, '2026-10-29T10:00:00Z'),
        ];

        $picked = new ReleasePolicy()->pick($releases, 1, new DateTimeImmutable(self::NOW));

        $this->assertSame('1.33.1', $picked->version());
    }

    /**
     * @return iterable<string, array{string, int, int, string}> expected version, then minor, patch and publication
     *     of the newest release next to 1.33.0
     */
    public static function cooldownBoundaries(): iterable
    {
        yield 'patch exactly 3 days old' => ['1.33.1', 33, 1, '2026-11-12T12:00:00Z'];
        yield 'patch one second short of 3 days' => ['1.33.0', 33, 1, '2026-11-12T12:00:01Z'];
        yield 'minor exactly 7 days old' => ['1.34.0', 34, 0, '2026-11-08T12:00:00Z'];
        yield 'minor 3 days old' => ['1.33.0', 34, 0, '2026-11-12T12:00:00Z'];
        yield 'minor one second short of 7 days' => ['1.33.0', 34, 0, '2026-11-08T12:00:01Z'];
    }

    #[Test]
    #[DataProvider('cooldownBoundaries')]
    public function it_waits_three_days_for_a_patch_and_seven_for_a_minor(string $expected, int $minor, int $patch, string $publishedAt): void
    {
        $releases = [$this->release(1, 33, 0, '2026-10-27T10:00:00Z'), $this->release(1, $minor, $patch, $publishedAt)];

        $picked = new ReleasePolicy()->pick($releases, 1, new DateTimeImmutable(self::NOW));

        $this->assertSame($expected, $picked->version());
    }

    #[Test]
    public function it_never_picks_a_release_of_another_major(): void
    {
        $releases = [
            $this->release(2, 0, 0, '2026-10-01T10:00:00Z'),
            $this->release(0, 99, 0, '2026-09-01T10:00:00Z'),
            $this->release(1, 33, 1, '2026-10-29T10:00:00Z'),
        ];

        $picked = new ReleasePolicy()->pick($releases, 1, new DateTimeImmutable(self::NOW));

        $this->assertSame('1.33.1', $picked->version());
    }

    #[Test]
    public function it_refuses_when_no_release_of_the_major_has_passed_its_cooldown(): void
    {
        $releases = [
            $this->release(1, 34, 0, '2026-11-14T10:00:00Z'),
            $this->release(2, 0, 0, '2026-10-01T10:00:00Z'),
        ];

        $this->expectExceptionObject(new NoEligibleRelease('No Opengrep 1.x release has passed its cooldown.'));

        new ReleasePolicy()->pick($releases, 1, new DateTimeImmutable(self::NOW));
    }

    #[Test]
    public function it_reports_the_newest_higher_major_once_it_is_fourteen_days_old(): void
    {
        $releases = [
            $this->release(1, 33, 1, '2026-10-29T10:00:00Z'),
            $this->release(2, 0, 0, '2026-11-01T12:00:00Z'),
            $this->release(2, 0, 1, '2026-11-01T12:00:00Z'),
            $this->release(2, 1, 0, '2026-11-14T10:00:00Z'),
        ];

        $reported = new ReleasePolicy()->newerMajor($releases, 1, new DateTimeImmutable(self::NOW));

        $this->assertSame('2.0.1', $reported?->version());
    }

    #[Test]
    public function it_stays_silent_about_a_higher_major_younger_than_fourteen_days(): void
    {
        $releases = [
            $this->release(1, 33, 1, '2026-10-29T10:00:00Z'),
            $this->release(2, 0, 0, '2026-11-01T12:00:01Z'),
        ];

        $reported = new ReleasePolicy()->newerMajor($releases, 1, new DateTimeImmutable(self::NOW));

        $this->assertNull($reported);
    }

    private function release(int $major, int $minor, int $patch, string $publishedAt): Release
    {
        return new Release($major, $minor, $patch, new DateTimeImmutable($publishedAt));
    }
}
