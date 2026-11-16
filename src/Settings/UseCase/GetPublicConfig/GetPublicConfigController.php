<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetPublicConfig;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetPublicConfigController
{
    public function __construct(
        private GetPublicConfigHandler $handler,
    ) {
    }

    #[Route('/config', name: 'settings_get_public_config', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Name, colours, images, texts and staffing of the association; the contact and the group for a member only.',
        content: new OA\JsonContent(ref: new Model(type: GetPublicConfigOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetPublicConfig()));
    }
}
