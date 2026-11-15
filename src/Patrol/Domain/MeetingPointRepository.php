<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use Symfony\Component\Uid\Uuid;

interface MeetingPointRepository
{
    /**
     * @throws NotFound
     */
    public function get(Uuid $id): MeetingPoint;

    /**
     * Archived items included, as their names stay taken.
     */
    public function findByNameKey(LookupKey $nameKey): ?MeetingPoint;

    /**
     * Stores the meeting point when the running transaction commits.
     */
    public function add(MeetingPoint $meetingPoint): void;
}
