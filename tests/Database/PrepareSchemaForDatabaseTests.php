<?php

declare(strict_types=1);

namespace App\Tests\Database;

use Override;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use PHPUnit\Metadata\Group;

/**
 * Prepares the schema once, before the first test, and only when the selected tests include the group database:
 * a run of unit tests never waits for a database.
 */
final readonly class PrepareSchemaForDatabaseTests implements ExecutionStartedSubscriber
{
    private const string GROUP = 'database';

    #[Override]
    public function notify(ExecutionStarted $event): void
    {
        foreach ($event->testSuite()->tests()->asArray() as $test) {
            if (!$test->isTestMethod() || !$this->isDatabaseTest($test)) {
                continue;
            }

            TestDatabase::prepared(SchemaPreparation::fromEmpty());

            return;
        }
    }

    private function isDatabaseTest(TestMethod $test): bool
    {
        foreach ($test->metadata()->isGroup() as $group) {
            if ($group instanceof Group && self::GROUP === $group->groupName()) {
                return true;
            }
        }

        return false;
    }
}
