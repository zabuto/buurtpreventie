<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ExportOwnData;

use App\Identity\UseCase\ListApiTokens\ListApiTokensOutput;
use App\Membership\UseCase\GetOwnProfile\GetOwnProfileOutput;
use App\Patrol\UseCase\ExportOwnPatrolData\ExportOwnPatrolDataOutput;

final readonly class ExportOwnDataOutput
{
    /**
     * @param list<array{occurredAt: string, action: string, subjectType: string, subjectId: string|null, channel: string, changes: array<string, array{old: scalar|null, new: scalar|null}>}> $activity
     *     every entry, newest first, as ListOwnActivityOutput gives them
     */
    public function __construct(
        public string $exportedAt,
        public GetOwnProfileOutput $profile,
        public ExportOwnPatrolDataOutput $patrol,
        public ListApiTokensOutput $tokens,
        public array $activity,
    ) {
    }
}
