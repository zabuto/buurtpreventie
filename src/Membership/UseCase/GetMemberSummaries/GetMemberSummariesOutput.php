<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMemberSummaries;

use OpenApi\Attributes as OA;

final readonly class GetMemberSummariesOutput
{
    /**
     * @param list<array{id: string, displayName: string|null, name: string|null, phone: string|null, address: string|null}> $members
     *     active members only; name null unless the reader coordinates or administers, phone and address null without
     *     consent or when the member filled in none
     */
    public function __construct(
        #[OA\Property(items: new OA\Items(
            required: ['id', 'displayName', 'name', 'phone', 'address'],
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'displayName', type: ['string', 'null']),
                new OA\Property(property: 'name', description: 'Null unless the reader coordinates or administers.', type: ['string', 'null']),
                new OA\Property(property: 'phone', description: 'Null without consent, or when the member filled in none.', type: ['string', 'null']),
                new OA\Property(property: 'address', description: 'Null without consent, or when the member filled in none.', type: ['string', 'null']),
            ],
            type: 'object',
        ))]
        public array $members,
    ) {
    }
}
