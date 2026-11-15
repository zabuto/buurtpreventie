<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

/**
 * Runs PHPStan in a separate process for tests that judge its output.
 *
 * Every run gets a fresh tmpDir: a result cache left by an earlier run could otherwise hide a change to the rules
 * and report a stale outcome. Stdout and stderr stay apart, so the JSON report is never mixed with warnings, and a
 * failure message shows both.
 */
final readonly class PhpStanProcess
{
    private const string PROJECT_ROOT = __DIR__.'/../..';

    private function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public static function analyse(string $configuration): self
    {
        $workDir = sys_get_temp_dir().'/phpstan-run-'.bin2hex(random_bytes(6));
        mkdir($workDir.'/cache', 0o777, true);

        $wrapper = $workDir.'/phpstan.neon';
        file_put_contents($wrapper, \sprintf(
            "includes:\n    - %s\nparameters:\n    tmpDir: %s/cache\n",
            (string) realpath($configuration),
            $workDir,
        ));

        try {
            return self::run([
                \PHP_BINARY,
                'vendor/bin/phpstan',
                'analyse',
                '--configuration='.$wrapper,
                '--error-format=json',
                '--no-progress',
                '--memory-limit=512M',
            ], $workDir.'/stderr.txt');
        } finally {
            self::remove($workDir);
        }
    }

    /**
     * @return array<mixed>|null the decoded JSON report, or null when stdout holds no JSON
     */
    public function report(): ?array
    {
        $report = json_decode($this->stdout, true);

        return \is_array($report) ? $report : null;
    }

    public function describe(): string
    {
        return \sprintf("PHPStan exited with %d.\n--- stdout ---\n%s\n--- stderr ---\n%s", $this->exitCode, $this->stdout, $this->stderr);
    }

    /**
     * @param list<string> $command
     */
    private static function run(array $command, string $stderrFile): self
    {
        // Stderr goes to a file: reading two pipes one after the other can deadlock once the second one fills up.
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', $stderrFile, 'w']], $pipes, self::PROJECT_ROOT);
        if (!\is_resource($process)) {
            return new self(-1, '', 'Could not start PHPStan.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        return new self($exitCode, $stdout, (string) file_get_contents($stderrFile));
    }

    private static function remove(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            \assert($entry instanceof \SplFileInfo);
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($path);
    }
}
