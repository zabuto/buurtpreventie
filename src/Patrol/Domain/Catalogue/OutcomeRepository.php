<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

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
     * Writes the new outcome within the running transaction, so a clash with another outcome surfaces here.
     *
     * @throws OutcomeNameTaken when another outcome committed the same name in the meantime
     */
    public function add(Outcome $outcome): void;

    /**
     * Writes the changes to the outcome within the running transaction, so a clash with another outcome surfaces here.
     *
     * @throws OutcomeNameTaken when another outcome committed the same name in the meantime
     */
    public function update(Outcome $outcome): void;

    /**
     * @return list<Outcome> archived ones included, by name
     */
    public function all(): array;
}
