<?php

declare(strict_types=1);

namespace App\Maintenance\UseCase\CheckHealth;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class CheckHealthController
{
    public function __construct(private CheckHealthHandler $handler)
    {
    }

    #[Route('/health', name: 'maintenance_check_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new CheckHealth()));
    }
}
