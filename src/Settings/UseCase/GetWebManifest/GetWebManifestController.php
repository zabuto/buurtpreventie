<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetWebManifest;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetWebManifestController
{
    public function __construct(
        private GetWebManifestHandler $handler,
    ) {
    }

    /**
     * The manifest standard names its members in snake case, unlike every other answer of the API.
     */
    #[Route('/manifest.webmanifest', name: 'settings_get_web_manifest', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'A web app manifest (W3C) with the name, colour and icon of the association.',
        content: new OA\MediaType(mediaType: 'application/manifest+json', schema: new OA\Schema(type: 'object')),
    )]
    public function __invoke(): JsonResponse
    {
        $manifest = ($this->handler)(new GetWebManifest());

        return new JsonResponse(
            [
                'name' => $manifest->name,
                'short_name' => $manifest->shortName,
                'lang' => $manifest->language,
                'start_url' => $manifest->startUrl,
                'scope' => $manifest->scope,
                'display' => $manifest->display,
                'theme_color' => $manifest->themeColor,
                'background_color' => $manifest->backgroundColor,
                'icons' => $manifest->icons,
            ],
            headers: ['Content-Type' => 'application/manifest+json'],
        );
    }
}
