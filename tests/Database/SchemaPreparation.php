<?php

declare(strict_types=1);

namespace App\Tests\Database;

/**
 * Builds the test database from empty with the migrations, through bin/console in separate processes.
 *
 * Separate processes keep the DDL away from the static connection of DAMA: MariaDB and MySQL commit every DDL
 * statement on the spot, which would end the transaction that rolls each test back.
 */
final readonly class SchemaPreparation
{
    private const string PROJECT_ROOT = __DIR__ . '/../..';

    public static function fromEmpty(): DatabaseAvailability
    {
        $failure = self::console('doctrine:database:drop', '--force', '--if-exists');
        if (null !== $failure) {
            return DatabaseAvailability::unreachable($failure);
        }

        $failure = self::console('doctrine:database:create') ?? self::console('doctrine:migrations:migrate', '--allow-no-migration');
        if (null !== $failure) {
            return DatabaseAvailability::broken($failure);
        }

        return DatabaseAvailability::ready();
    }

    /**
     * @return string|null the output of a failed command, on one line; null on success
     */
    private static function console(string ...$arguments): ?string
    {
        $stdout = self::temporaryFile();
        $stderr = self::temporaryFile();

        try {
            $process = \proc_open(
                [\PHP_BINARY, 'bin/console', ...\array_values($arguments), '--env=test', '--no-interaction'],
                [1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']],
                $pipes,
                self::PROJECT_ROOT,
                // Symfony Console wraps its error blocks at the terminal width, which would split words in the message.
                [...\getenv(), 'COLUMNS' => '1000'],
            );
            if (!\is_resource($process)) {
                return 'Could not start bin/console.';
            }

            if (0 === \proc_close($process)) {
                return null;
            }

            $output = (string) \file_get_contents($stdout) . ' ' . (string) \file_get_contents($stderr);

            return \sprintf('%s: %s', \implode(' ', $arguments), \trim((string) \preg_replace('/\s+/', ' ', $output)));
        } finally {
            // A file is missing when the process could not start.
            \array_map(\unlink(...), \array_filter([$stdout, $stderr], \is_file(...)));
        }
    }

    /**
     * @return non-empty-string
     */
    private static function temporaryFile(): string
    {
        return \sys_get_temp_dir() . '/schema-' . \bin2hex(\random_bytes(6)) . '.txt';
    }
}
