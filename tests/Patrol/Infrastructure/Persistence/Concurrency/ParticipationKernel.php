<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence\Concurrency;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The test kernel and its private services, for the test and for the process it races, which runs outside PHPUnit.
 * Abstract, so PHPUnit looks for no tests in it.
 */
abstract class ParticipationKernel extends KernelTestCase
{
    public static function handlers(): ParticipationHandlers
    {
        $container = self::getContainer();
        $registry = $container->get(ManagerRegistry::class);
        $tokenStorage = $container->get(TokenStorageInterface::class);
        $checker = $container->get(AuthorizationCheckerInterface::class);
        // @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue (PHPStan reads the types from the container, Mago does not)
        \assert($registry instanceof ManagerRegistry, 'Doctrine registers its manager registry.');
        // @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue (PHPStan reads the types from the container, Mago does not)
        \assert($tokenStorage instanceof TokenStorageInterface, 'The security bundle registers the token storage.');
        // @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue (PHPStan reads the types from the container, Mago does not)
        \assert($checker instanceof AuthorizationCheckerInterface, 'The security bundle registers the authorization checker.');

        return ParticipationHandlers::on($registry, $tokenStorage, $checker);
    }
}
