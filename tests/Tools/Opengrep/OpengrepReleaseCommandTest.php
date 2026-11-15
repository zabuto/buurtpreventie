<?php

declare(strict_types=1);

namespace App\Tests\Tools\Opengrep;

use App\Tools\Opengrep\CommandResult;
use App\Tools\Opengrep\OpengrepReleaseCommand;
use App\Tools\Opengrep\Release;
use App\Tools\Opengrep\ReleasePolicy;
use App\Tools\Opengrep\ReleaseSource;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OpengrepReleaseCommandTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00Z';
    private const string NEW_MAJOR_WARNING = "Opengrep 2.0.1 is a new major version, released 2026-11-01. CI keeps using 1.x until a pull request changes tools/opengrep-major; see docs/operations.md.\n";

    #[Test]
    public function it_prints_the_version_ci_installs(): void
    {
        $command = $this->command([new Release(1, 33, 1, new DateTimeImmutable('2026-10-29T10:00:00Z'))]);

        $result = $command->run(['select'], 1);

        $this->assertEquals(new CommandResult(0, "1.33.1\n", ''), $result);
    }

    #[Test]
    public function it_fails_the_selection_when_no_release_has_passed_its_cooldown(): void
    {
        $command = $this->command([new Release(1, 34, 0, new DateTimeImmutable('2026-11-14T10:00:00Z'))]);

        $result = $command->run(['select'], 1);

        $this->assertEquals(new CommandResult(1, '', "No Opengrep 1.x release has passed its cooldown.\n"), $result);
    }

    #[Test]
    public function it_fails_the_selection_when_github_cannot_be_reached(): void
    {
        $command = $this->unreachableCommand();

        $result = $command->run(['select'], 1);

        $this->assertEquals(new CommandResult(1, '', "The GitHub API could not be reached.\n"), $result);
    }

    #[Test]
    public function it_warns_about_a_new_major_without_failing(): void
    {
        $command = $this->command($this->releasesWithNewMajor());

        $result = $command->run(['check-major'], 1);

        $this->assertEquals(new CommandResult(0, self::NEW_MAJOR_WARNING, ''), $result);
    }

    #[Test]
    public function it_fails_on_a_new_major_when_asked_to(): void
    {
        $command = $this->command($this->releasesWithNewMajor());

        $result = $command->run(['check-major', '--fail'], 1);

        $this->assertEquals(new CommandResult(1, self::NEW_MAJOR_WARNING, ''), $result);
    }

    #[Test]
    public function it_stays_silent_without_a_new_major(): void
    {
        $command = $this->command([new Release(1, 33, 1, new DateTimeImmutable('2026-10-29T10:00:00Z'))]);

        $result = $command->run(['check-major', '--fail'], 1);

        $this->assertEquals(new CommandResult(0, '', ''), $result);
    }

    /**
     * Composer runs the check after every update; a missing network must never stop that update.
     */
    #[Test]
    public function it_stays_silent_about_an_unreachable_github_during_an_update(): void
    {
        $command = $this->unreachableCommand();

        $result = $command->run(['check-major'], 1);

        $this->assertEquals(new CommandResult(0, '', ''), $result);
    }

    #[Test]
    public function it_fails_the_check_on_an_unreachable_github_when_asked_to(): void
    {
        $command = $this->unreachableCommand();

        $result = $command->run(['check-major', '--fail'], 1);

        $this->assertEquals(new CommandResult(1, '', "The GitHub API could not be reached.\n"), $result);
    }

    #[Test]
    public function it_refuses_an_unknown_command(): void
    {
        $command = $this->command([]);

        $result = $command->run(['update'], 1);

        $this->assertEquals(new CommandResult(2, '', "Usage: opengrep-release.php select | check-major [--fail]\n"), $result);
    }

    /**
     * @return list<Release>
     */
    private function releasesWithNewMajor(): array
    {
        return [
            new Release(1, 33, 1, new DateTimeImmutable('2026-10-29T10:00:00Z')),
            new Release(2, 0, 1, new DateTimeImmutable('2026-11-01T09:00:00Z')),
        ];
    }

    /**
     * @param list<Release> $releases
     */
    private function command(array $releases): OpengrepReleaseCommand
    {
        $source = new readonly class($releases) implements ReleaseSource {
            /**
             * @param list<Release> $releases
             */
            public function __construct(
                private array $releases,
            ) {
            }

            public function releases(): array
            {
                return $this->releases;
            }
        };

        return new OpengrepReleaseCommand($source, new ReleasePolicy(), new DateTimeImmutable(self::NOW));
    }

    private function unreachableCommand(): OpengrepReleaseCommand
    {
        $source = new readonly class implements ReleaseSource {
            public function releases(): array
            {
                throw new RuntimeException('The GitHub API could not be reached.');
            }
        };

        return new OpengrepReleaseCommand($source, new ReleasePolicy(), new DateTimeImmutable(self::NOW));
    }
}
