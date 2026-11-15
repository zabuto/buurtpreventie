<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\NotFound;
use DateTimeImmutable;
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

    /**
     * The rounds that have not started with this member signed up, locked for writing until the running transaction
     * ends, so a sign-up or withdrawal of another walker waits instead of counting the walkers wrongly.
     *
     * @return list<Round>
     */
    public function upcomingWithWalker(Uuid $memberId, DateTimeImmutable $now): array;
}
