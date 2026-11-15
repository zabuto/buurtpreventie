<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use Symfony\Component\Uid\Uuid;

interface OutcomeRepository
{
    /**
     * @throws NotFound
     */
    public function get(Uuid $id): Outcome;

    /**
     * Archived items included, as their names stay taken.
     */
    public function findByNameKey(LookupKey $nameKey): ?Outcome;

    /**
     * Stores the outcome when the running transaction commits.
     */
    public function add(Outcome $outcome): void;
}
