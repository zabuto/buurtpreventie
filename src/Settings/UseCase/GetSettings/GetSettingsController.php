<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetSettings;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetSettingsController
{
    public function __construct(
        private GetSettingsHandler $handler,
    ) {
    }

    #[Route('/admin/settings', name: 'settings_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Every setting with the value that counts, and which of them hold their default.',
        content: new OA\JsonContent(ref: new Model(type: GetSettingsOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetSettings()));
    }
}
