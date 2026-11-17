<?php

declare(strict_types=1);

namespace App\Tests\Database;

use Override;
use PHPUnit\Event\Application\Finished;
use PHPUnit\Event\Application\FinishedSubscriber;

/**
 * Drops the test database of a run with a token of its own when the run ends, failed tests or not.
 *
 * Not on an earlier event: the test bundle keeps a transaction open on its static connection until the test runner
 * finishes, and DROP DATABASE waits for that transaction without a time limit. A failed drop stays silent: the result
 * is printed, and docs/testing.md says how to remove a database a run left behind.
 */
final readonly class DropRunDatabase implements FinishedSubscriber
{
    #[Override]
    public function notify(Finished $event): void
    {
        if (!TestDatabase::reachedTheServer()) {
            return;
        }

        SchemaPreparation::drop();
    }
}
