<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetPublicConfig;

use OpenApi\Attributes as OA;

final readonly class GetPublicConfigOutput
{
    /**
     * @param string                                                  $welcomeText   plain text in the language of the association; an empty line starts a paragraph
     * @param string                                                  $emergencyText plain text in the language of the association; an empty line starts a paragraph
     * @param ?string                                                 $logoUrl       the logo within 512 × 512, as a path from the root of the host; null without a logo
     * @param list<array{size: int, url: string}>                     $icons         every size of the app icon, as a path from the root of the host
     * @param array{name: ?string, email: ?string, phone: ?string}    $contact       the coordinator, for a logged-in member only
     * @param array{url: ?string, service: ?string}                   $group         the group of the association, for anyone
     */
    public function __construct(
        public string $associationName,
        public string $associationShortName,
        public string $primaryColor,
        public string $accentColor,
        public string $primaryDarkColor,
        public string $accentDarkColor,
        public ?string $logoUrl,
        #[OA\Property(items: new OA\Items(
            required: ['size', 'url'],
            properties: [
                new OA\Property(property: 'size', description: 'Width and height in pixels: 512, 192, 180 (Apple) or 32 (favicon).', type: 'integer'),
                new OA\Property(property: 'url', type: 'string'),
            ],
            type: 'object',
        ))]
        public array $icons,
        public string $language,
        public string $welcomeText,
        public string $emergencyText,
        public ?string $privacyUrl,
        public ?string $accessibilityUrl,
        public int $minimumWalkers,
        public int $maximumWalkers,
        #[OA\Property(
            description: 'Null values unless a member is logged in.',
            required: ['name', 'email', 'phone'],
            properties: [
                new OA\Property(property: 'name', type: ['string', 'null']),
                new OA\Property(property: 'email', type: ['string', 'null']),
                new OA\Property(property: 'phone', type: ['string', 'null']),
            ],
            type: 'object',
        )]
        public array $contact,
        #[OA\Property(
            description: 'Null values when the association has no group.',
            required: ['url', 'service'],
            properties: [
                new OA\Property(property: 'url', type: ['string', 'null']),
                new OA\Property(property: 'service', type: ['string', 'null'], enum: ['whatsapp', 'signal', null]),
            ],
            type: 'object',
        )]
        public array $group,
    ) {
    }
}
