<?php

declare(strict_types=1);

namespace App\Maintenance\UseCase\CheckHealth;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class CheckHealthController
{
    public function __construct(
        private CheckHealthHandler $handler,
    ) {
    }

    #[Route('/health', name: 'maintenance_check_health', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The app answers.',
        content: new OA\JsonContent(ref: new Model(type: CheckHealthOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new CheckHealth()));
    }
}
