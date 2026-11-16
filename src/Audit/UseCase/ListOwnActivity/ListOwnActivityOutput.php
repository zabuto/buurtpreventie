<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListOwnActivity;

final readonly class ListOwnActivityOutput
{
    /**
     * @param list<array{occurredAt: string, action: string, subjectType: string, subjectId: string|null, channel: string, changes: array<string, array{old: scalar|null, new: scalar|null}>}> $entries
     *     subject id and changes are left out when the subject is another member
     * @param int|null $nextPage null on the last page
     */
    public function __construct(
        public array $entries,
        public ?int $nextPage,
    ) {
    }
}
