<?php

declare(strict_types=1);

namespace App\Settings\UseCase\ChangeBrandImage;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ChangeBrandImageController
{
    public function __construct(
        private ChangeBrandImageHandler $handler,
    ) {
    }

    /**
     * The body is the file itself, not a form: its Content-Type, name and extension count for nothing.
     */
    #[Route('/admin/settings/images/{kind}', name: 'settings_change_brand_image', requirements: ['kind' => 'logo|icon'], methods: ['PUT'])]
    #[OA\RequestBody(
        description: 'A PNG or JPEG of at most 2 MB and 2048 × 2048 pixels; an icon is square and at least 512 × 512.',
        required: true,
        content: [
            new OA\MediaType(mediaType: 'image/png', schema: new OA\Schema(type: 'string', format: 'binary')),
            new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary')),
        ],
    )]
    #[OA\Response(response: 204, description: 'Stored in every size the app uses.')]
    public function __invoke(Request $request, string $kind): Response
    {
        $body = $request->getContent(asResource: true);
        ($this->handler)(new ChangeBrandImage($kind, (string) \stream_get_contents($body, ChangeBrandImage::READ_AT_MOST)));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
