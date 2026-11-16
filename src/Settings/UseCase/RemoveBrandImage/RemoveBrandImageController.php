<?php

declare(strict_types=1);

namespace App\Settings\UseCase\RemoveBrandImage;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class RemoveBrandImageController
{
    public function __construct(
        private RemoveBrandImageHandler $handler,
    ) {
    }

    #[Route('/admin/settings/images/{kind}', name: 'settings_remove_brand_image', requirements: ['kind' => 'logo|icon'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'The default image is in use.')]
    public function __invoke(string $kind): Response
    {
        ($this->handler)(new RemoveBrandImage($kind));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
