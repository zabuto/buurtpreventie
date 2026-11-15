<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

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
     * Writes the new meeting point within the running transaction, so a clash with another meeting point surfaces here.
     *
     * @throws MeetingPointNameTaken when another meeting point committed the same name in the meantime
     */
    public function add(MeetingPoint $meetingPoint): void;

    /**
     * Writes the changes to the meeting point within the running transaction, so a clash with another meeting point surfaces here.
     *
     * @throws MeetingPointNameTaken when another meeting point committed the same name in the meantime
     */
    public function update(MeetingPoint $meetingPoint): void;
}
