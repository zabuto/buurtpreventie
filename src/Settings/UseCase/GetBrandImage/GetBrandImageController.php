<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetBrandImage;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetBrandImageController
{
    /**
     * Symfony marks a response private when the request touched a session, as the browser firewall does for a logged-in
     * member; this header keeps the cache headers below, since the image is the same for everyone.
     */
    private const string KEEP_CACHE_HEADERS = 'Symfony-Session-NoAutoCacheControl';

    public function __construct(
        private GetBrandImageHandler $handler,
    ) {
    }

    /**
     * A new image gets new file names, so a file never changes and may be cached for a year.
     */
    #[Route('/media/brand/{file}', name: 'settings_get_brand_image', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'One size of the logo or icon.',
        content: new OA\MediaType(mediaType: 'image/png', schema: new OA\Schema(type: 'string', format: 'binary')),
    )]
    public function __invoke(string $file): Response
    {
        $output = ($this->handler)(new GetBrandImage($file));

        return new Response($output->content, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            self::KEEP_CACHE_HEADERS => 'true',
        ]);
    }
}
