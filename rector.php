<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
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
        // Every handler takes its input, also while the input is still empty: REST and MCP pass the same object.
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__.'/src/*/UseCase/*'],
        // The test converts through the Type contract, as Doctrine does; with the narrow type PHPStan would deem the
        // checks on null and on the class of the result always true.
        NarrowObjectReturnTypeRector::class => [__DIR__.'/tests/Shared/Infrastructure/Doctrine/UtcDateTimeImmutableTypeTest.php'],
    ])
    ->withPhpSets(php84: true)
    ->withComposerBased(symfony: true, phpunit: true)
    ->withAttributesSets()
    ->withPreparedSets(deadCode: true, typeDeclarations: true)
    ->withSymfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml');
