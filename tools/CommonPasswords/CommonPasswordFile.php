<?php

declare(strict_types=1);

namespace App\Tools\CommonPasswords;

/**
 * The list of common passwords the app reads: the Dutch passwords of tools/common-passwords-nl.txt first, then
 * 10k-most-common.txt of SecLists at a fixed commit, each password once, in lower case and without whitespace, the form
 * GuessablePasswords compares in.
 */
final readonly class CommonPasswordFile
{
    public const string SOURCE_PATH = 'Passwords/Common-Credentials/10k-most-common.txt';

    private const string COMMIT_LINE = '# SecLists commit: ';

    public static function build(string $commit, string $licence, string $secLists, string $dutch): string
    {
        $passwords = \array_values(\array_unique([...self::entries($dutch), ...self::entries($secLists)]));
        $licenceLines = \array_map(
            static fn(string $line): string => \rtrim('# ' . $line),
            \explode("\n", \rtrim(\str_replace("\r\n", "\n", $licence))),
        );

        return \implode("\n", [
            '# Passwords too common to be safe, one per line, in lower case and without whitespace, the form',
            '# GuessablePasswords compares in. Lines starting with # are comments; no listed password starts with one.',
            '# Built by php tools/common-passwords.php; edit tools/common-passwords-nl.txt, not this file.',
            '#',
            '# First the Dutch passwords of tools/common-passwords-nl.txt, written for this app. Then',
            '# ' . self::SOURCE_PATH . ' from SecLists (https://github.com/danielmiessler/SecLists),',
            '# under its MIT licence:',
            self::COMMIT_LINE . $commit,
            '#',
            ...$licenceLines,
            ...$passwords,
        ]) . "\n";
    }

    /**
     * @return list<string> the passwords of a built file, in its order
     */
    public static function passwords(string $file): array
    {
        return \array_values(\array_filter(
            \explode("\n", $file),
            static fn(string $line): bool => '' !== $line && !\str_starts_with($line, '#'),
        ));
    }

    public static function commitOf(string $file): ?string
    {
        foreach (\explode("\n", $file) as $line) {
            if (\str_starts_with($line, self::COMMIT_LINE)) {
                return \substr($line, \strlen(self::COMMIT_LINE));
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function entries(string $source): array
    {
        $entries = [];
        foreach (\explode("\n", $source) as $line) {
            $password = \mb_strtolower((string) \preg_replace('/\s+/u', '', $line));
            if ('' === $password || \str_starts_with($password, '#')) {
                continue;
            }

            $entries[] = $password;
        }

        return $entries;
    }
}
