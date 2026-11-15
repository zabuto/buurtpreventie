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
     * Locks the round and its walkers for writing until the running transaction ends, and reads the walkers with
     * that locking read. Call it before anything else in the transaction: InnoDB fixes the snapshot of a plain read
     * at the first one, and a walker who signed up meanwhile would go uncounted.
     *
     * @throws NotFound
     */
    public function getForUpdate(Uuid $id): Round;

    /**
     * @return list<Round> starting from $from up to, not including, $to, by start
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return list<Round> the rounds that have not started, by start
     */
    public function upcoming(DateTimeImmutable $now): array;

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
