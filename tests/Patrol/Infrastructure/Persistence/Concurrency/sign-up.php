<?php

declare(strict_types=1);

/**
 * Signs a walker up for a round in a process of its own and prints the outcome: "signed up" or the short name of
 * the exception. Usage: php sign-up.php <round id> <member id>
 */

use App\Patrol\UseCase\SignUpForRound\SignUpForRound;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Infrastructure\Persistence\Concurrency\ParticipationKernel;
use Symfony\Component\Uid\Uuid;

require __DIR__ . '/../../../../bootstrap.php';

$arguments = $_SERVER['argv'] ?? [];
\assert(\is_array($arguments), 'PHP passes the arguments of a script as a list.');
$roundId = $arguments[1] ?? null;
$memberId = $arguments[2] ?? null;
if (!\is_string($roundId) || !\is_string($memberId)) {
    exit('Usage: php sign-up.php <round id> <member id>');
}

$handlers = ParticipationKernel::handlers();

try {
    $handlers->runAs->member(
        new Actor(Uuid::fromString($memberId), [Role::Walker]),
        Channel::Console,
        static fn() => ($handlers->signUp)(new SignUpForRound($roundId)),
    );
    echo 'signed up';
} catch (Throwable $exception) {
    echo new ReflectionClass($exception)->getShortName();
}
