<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Symfony\Symfony80\Rector\Class_\RemoveEraseCredentialsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/bin',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/tools',
    ])
    ->withSkip([
        // Recipe files stay exactly as Symfony ships them, so a recipe update applies cleanly.
        __DIR__.'/config/bundles.php',
        __DIR__.'/config/preload.php',
        __DIR__.'/config/reference.php',
        __DIR__.'/public/index.php',
        __DIR__.'/src/Kernel.php',
        __DIR__.'/tests/bootstrap.php',
        __DIR__.'/tests/Architecture/Fixtures',
        // Every handler takes its input, even an empty one: REST and MCP pass the same object.
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__.'/src/*/UseCase/*'],
        // The test converts through the Type contract, as Doctrine does; the narrow type would make PHPStan deem the
        // null and class checks on the result always true.
        // An entity id is a Uuid by contract; that it is made as UUIDv7 is no promise to its callers.
        NarrowObjectReturnTypeRector::class => [
            __DIR__.'/tests/Shared/Infrastructure/Doctrine/UtcDateTimeImmutableTypeTest.php',
            __DIR__.'/src/*/Domain/*',
        ],
        // Symfony 7.4 still declares eraseCredentials() in UserInterface, so the empty method stays until the app runs
        // on Symfony 8 only. @upgrade-8.4 Drop these two skips and let the rules remove the methods.
        RemoveEraseCredentialsRector::class => [__DIR__.'/src/Shared/Infrastructure/Security'],
        RemoveEmptyClassMethodRector::class => [__DIR__.'/src/Shared/Infrastructure/Security'],
    ])
    ->withPhpSets(php84: true)
    ->withComposerBased(symfony: true, phpunit: true)
    ->withAttributesSets()
    ->withPreparedSets(deadCode: true, typeDeclarations: true)
    ->withSymfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml');
