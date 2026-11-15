<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence\Concurrency;

use App\Patrol\UseCase\SignUpForRound\SignUpForRoundHandler;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRoundHandler;
use App\Shared\Infrastructure\Security\RunAs;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The test kernel and its private services, for the test and for the process it races, which runs outside PHPUnit.
 * Abstract, so PHPUnit looks for no tests in it.
 */
abstract class ParticipationKernel extends KernelTestCase
{
    public static function handlers(): ParticipationHandlers
    {
        $container = self::getContainer();
        $signUp = $container->get(SignUpForRoundHandler::class);
        $withdraw = $container->get(WithdrawFromRoundHandler::class);
        $runAs = $container->get(RunAs::class);
        // @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue (PHPStan reads the types from the container, Mago does not)
        \assert($signUp instanceof SignUpForRoundHandler, 'The sample data keep the handler in the container.');
        // @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue (PHPStan reads the types from the container, Mago does not)
        \assert($withdraw instanceof WithdrawFromRoundHandler, 'The sample data keep the handler in the container.');
        // @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue (PHPStan reads the types from the container, Mago does not)
        \assert($runAs instanceof RunAs, 'The sample data keep RunAs in the container.');

        return new ParticipationHandlers($signUp, $withdraw, $runAs);
    }
}
