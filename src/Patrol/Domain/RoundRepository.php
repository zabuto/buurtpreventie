<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\NotFound;
use Symfony\Component\Uid\Uuid;

interface RoundRepository
{
    /**
     * @throws NotFound
     */
    public function get(Uuid $id): Round;

    /**
     * Stores the round when the running transaction commits.
     */
    public function add(Round $round): void;

    public function remove(Round $round): void;
}
