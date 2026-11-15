<?php

declare(strict_types=1);

namespace App\Tests\Database;

use Override;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Builds the test database from empty with the migrations, once per run, before the database tests. The recipe file
 * tests/bootstrap.php stays as Symfony ships it, so this cannot live there.
 */
final readonly class DatabaseSchemaExtension implements Extension
{
    #[Override]
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new PrepareSchemaForDatabaseTests());
    }
}
