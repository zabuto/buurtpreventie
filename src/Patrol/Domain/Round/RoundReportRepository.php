<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Round;

use App\Shared\Domain\NotFound;
use Symfony\Component\Uid\Uuid;

/**
 * Reports are stored and removed through their round; this repository only finds one by id.
 */
interface RoundReportRepository
{
    /**
     * @throws NotFound
     */
    public function get(Uuid $id): RoundReport;

    /**
     * @return list<RoundReport> oldest first
     */
    public function byAuthor(Uuid $authorId): array;
}
