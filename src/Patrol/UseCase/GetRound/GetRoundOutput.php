<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\GetRound;

use OpenApi\Attributes as OA;

final readonly class GetRoundOutput
{
    /**
     * @param array{id: string, name: string, latitude: float|null, longitude: float|null}|null $meetingPoint
     * @param list<array{id: string, displayName: string|null, name: string|null, phone: string|null}> $walkers
     *     active walkers only, earliest joined first; name and phone null for what the reader may not see
     * @param string|null $cancelReason why a cancelled round was not walked, when given
     * @param int $inactiveWalkerCount walkers who are deactivated, erased or unknown
     * @param array{
     *     id: string,
     *     outcome: string,
     *     outcomeKind: string,
     *     note: string|null,
     *     authorId: string,
     *     authorDisplayName: string|null,
     *     reportedAt: string,
     * }|null $report a null authorDisplayName is an erased member; the note is null for whoever may not read it
     * @param list<array{id: string, authorId: string, authorDisplayName: string|null, text: string, postedAt: string}> $comments
     *     oldest first, empty for whoever may not read them; a null authorDisplayName is an erased member
     */
    public function __construct(
        public string $id,
        public string $startsAt,
        public string $daypart,
        public string $status,
        public int $walkerCount,
        public int $minimumWalkers,
        public int $maximumWalkers,
        public int $openSpots,
        public ?array $meetingPoint,
        public ?string $startNote,
        public ?string $cancelReason,
        public ?string $ownerId,
        #[OA\Property(items: new OA\Items(
            required: ['id', 'displayName', 'name', 'phone'],
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'displayName', type: ['string', 'null']),
                new OA\Property(property: 'name', description: 'Null unless the reader coordinates or administers.', type: ['string', 'null']),
                new OA\Property(
                    property: 'phone',
                    description: 'Null for a reader who neither walks the round nor coordinates, without consent, or when none was filled in.',
                    type: ['string', 'null'],
                ),
            ],
            type: 'object',
        ))]
        public array $walkers,
        public int $inactiveWalkerCount,
        public ?array $report,
        public array $comments,
    ) {
    }
}
