<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\FindStaffingGaps;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class FindStaffingGapsController
{
    public function __construct(
        private FindStaffingGapsHandler $handler,
    ) {
    }

    #[Route('/staffing-gaps', name: 'patrol_find_staffing_gaps', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new FindStaffingGaps()));
    }
}
