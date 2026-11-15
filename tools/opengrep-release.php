<?php

declare(strict_types=1);

use App\Tools\Opengrep\GitHubReleaseSource;
use App\Tools\Opengrep\OpengrepReleaseCommand;
use App\Tools\Opengrep\ReleasePolicy;

require \dirname(__DIR__) . '/vendor/autoload.php';

$major = (int) \trim((string) \file_get_contents(__DIR__ . '/opengrep-major'));
$token = \getenv('GITHUB_TOKEN');

$command = new OpengrepReleaseCommand(
    new GitHubReleaseSource(timeoutSeconds: 5.0, token: false === $token ? null : $token),
    new ReleasePolicy(),
    new DateTimeImmutable('now', new DateTimeZone('UTC')),
);
/** @var list<string> $argv */
$result = $command->run(\array_slice($argv, 1), $major);

\fwrite(\STDOUT, $result->output);
\fwrite(\STDERR, $result->error);

exit($result->exitCode);
