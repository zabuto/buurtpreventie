<?php

declare(strict_types=1);

namespace App\Tools\Opengrep;

use DateTimeImmutable;
use RuntimeException;

/**
 * `select` prints the Opengrep version CI installs. `check-major` reports a new major version: Composer runs it after
 * every update, where a missing network must never stop the update, and the weekly workflow runs it with `--fail`.
 */
final readonly class OpengrepReleaseCommand
{
    private const string USAGE = "Usage: opengrep-release.php select | check-major [--fail]\n";

    public function __construct(
        private ReleaseSource $source,
        private ReleasePolicy $policy,
        private DateTimeImmutable $now,
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments, int $major): CommandResult
    {
        return match ($arguments) {
            ['select'] => $this->select($major),
            ['check-major'] => $this->checkMajor($major, fail: false),
            ['check-major', '--fail'] => $this->checkMajor($major, fail: true),
            default => new CommandResult(2, '', self::USAGE),
        };
    }

    private function select(int $major): CommandResult
    {
        try {
            $release = $this->policy->pick($this->source->releases(), $major, $this->now);
        } catch (RuntimeException $exception) {
            return new CommandResult(1, '', $exception->getMessage() . "\n");
        }

        return new CommandResult(0, $release->version() . "\n", '');
    }

    private function checkMajor(int $major, bool $fail): CommandResult
    {
        try {
            $release = $this->policy->newerMajor($this->source->releases(), $major, $this->now);
        } catch (RuntimeException $exception) {
            return $fail ? new CommandResult(1, '', $exception->getMessage() . "\n") : new CommandResult(0, '', '');
        }

        if (null === $release) {
            return new CommandResult(0, '', '');
        }

        $warning = \sprintf(
            "Opengrep %s is a new major version, released %s. CI keeps using %d.x until a pull request changes tools/opengrep-major; see docs/operations.md.\n",
            $release->version(),
            $release->publishedAt->format('Y-m-d'),
            $major,
        );

        return new CommandResult($fail ? 1 : 0, $warning, '');
    }
}
