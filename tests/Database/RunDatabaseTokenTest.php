<?php

declare(strict_types=1);

namespace App\Tests\Database;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The run that executes these tests has claimed a token of its own; every test clears it first and puts it back
 * afterwards, so the database tests later in the run keep using the database of this run.
 */
final class RunDatabaseTokenTest extends TestCase
{
    private const string NAME = 'TEST_TOKEN';

    /** @var array{server: mixed, env: mixed, environment: string|false} */
    private array $original;

    #[Override]
    protected function setUp(): void
    {
        $this->original = [
            'server' => $_SERVER[self::NAME] ?? null,
            'env' => $_ENV[self::NAME] ?? null,
            'environment' => \getenv(self::NAME),
        ];
        unset($_SERVER[self::NAME], $_ENV[self::NAME]);
        \putenv(self::NAME);
    }

    #[Override]
    protected function tearDown(): void
    {
        unset($_SERVER[self::NAME], $_ENV[self::NAME]);
        if (null !== $this->original['server']) {
            $_SERVER[self::NAME] = $this->original['server'];
        }
        if (null !== $this->original['env']) {
            $_ENV[self::NAME] = $this->original['env'];
        }
        \putenv(false === $this->original['environment'] ? self::NAME : self::NAME . '=' . $this->original['environment']);
    }

    #[Test]
    public function it_gives_a_run_without_a_token_one_of_its_own(): void
    {
        $claimed = RunDatabaseToken::claim();

        $this->assertTrue($claimed);
        $token = \getenv(self::NAME);
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^_run[0-9a-f]{8}$/', $token);
        $this->assertSame([$token, $token], [$_SERVER[self::NAME] ?? null, $_ENV[self::NAME] ?? null]);
    }

    #[Test]
    public function it_treats_an_empty_token_as_none(): void
    {
        \putenv(self::NAME . '=');
        $_SERVER[self::NAME] = '';

        $claimed = RunDatabaseToken::claim();

        $this->assertTrue($claimed);
        $this->assertMatchesRegularExpression('/^_run[0-9a-f]{8}$/', (string) \getenv(self::NAME));
    }

    #[Test]
    public function it_gives_two_runs_different_databases(): void
    {
        RunDatabaseToken::claim();
        $first = \getenv(self::NAME);
        unset($_SERVER[self::NAME], $_ENV[self::NAME]);
        \putenv(self::NAME);

        RunDatabaseToken::claim();

        $this->assertNotSame($first, \getenv(self::NAME));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function outsideSources(): iterable
    {
        yield 'process environment' => ['environment'];
        yield 'server variable, as phpunit.xml <server> sets it' => ['server'];
        yield 'env variable, as Dotenv sets it' => ['env'];
    }

    #[DataProvider('outsideSources')]
    #[Test]
    public function it_keeps_a_token_set_from_outside_and_leaves_its_database_alone(string $source): void
    {
        match ($source) {
            'environment' => \putenv(self::NAME . '=_fix'),
            'server' => $_SERVER[self::NAME] = '_fix',
            default => $_ENV[self::NAME] = '_fix',
        };

        $claimed = RunDatabaseToken::claim();

        $this->assertFalse($claimed);
        $this->assertSame(
            ['environment' => 'environment' === $source ? '_fix' : false, 'server' => 'server' === $source ? '_fix' : null],
            ['environment' => \getenv(self::NAME), 'server' => $_SERVER[self::NAME] ?? null],
        );
    }

    #[Test]
    public function it_hands_its_token_to_the_processes_it_starts(): void
    {
        RunDatabaseToken::claim();

        $output = \sys_get_temp_dir() . '/run-token-' . \bin2hex(\random_bytes(6)) . '.txt';
        try {
            $process = \proc_open([\PHP_BINARY, '-r', 'echo getenv("TEST_TOKEN");'], [1 => ['file', $output, 'w']], $pipes);
            $this->assertIsResource($process);
            \proc_close($process);
            $childToken = \file_get_contents($output);
        } finally {
            if (\is_file($output)) {
                \unlink($output);
            }
        }

        $this->assertSame(\getenv(self::NAME), $childToken);
    }
}
