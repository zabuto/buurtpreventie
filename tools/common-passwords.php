<?php

declare(strict_types=1);

use App\Tools\CommonPasswords\CommonPasswordFile;
use App\Tools\CommonPasswords\SecListsSource;

require \dirname(__DIR__) . '/vendor/autoload.php';

// Usage: php tools/common-passwords.php [<SecLists commit>]
// Without a commit it rebuilds from the one the list names, which picks up a change to tools/common-passwords-nl.txt.
$target = \dirname(__DIR__) . '/src/Identity/Infrastructure/Password/common-passwords.txt';
$arguments = \is_array($_SERVER['argv'] ?? null) ? \array_slice($_SERVER['argv'], 1) : [];
$commit = \is_string($arguments[0] ?? null) ? $arguments[0] : CommonPasswordFile::commitOf((string) \file_get_contents($target));
if (null === $commit) {
    \fwrite(\STDERR, "The list names no SecLists commit; pass one.\n");
    exit(1);
}

$source = new SecListsSource(timeoutSeconds: 10.0);
try {
    $file = CommonPasswordFile::build(
        $commit,
        $source->file($commit, 'LICENSE'),
        $source->file($commit, CommonPasswordFile::SOURCE_PATH),
        (string) \file_get_contents(__DIR__ . '/common-passwords-nl.txt'),
    );
} catch (RuntimeException $exception) {
    \fwrite(\STDERR, $exception->getMessage() . "\n");
    exit(1);
}

\file_put_contents($target, $file);
\fwrite(\STDOUT, \sprintf("%d passwords from SecLists commit %s.\n", \count(CommonPasswordFile::passwords($file)), $commit));
