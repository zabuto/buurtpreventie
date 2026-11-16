<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListOutcomes;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListOutcomesController
{
    public function __construct(
        private ListOutcomesHandler $handler,
    ) {
    }

    /**
     * The list for members holds what they can choose; the one under /admin/ holds the archived items too.
     */
    #[Route('/outcomes', name: 'patrol_list_outcomes', methods: ['GET'])]
    #[Route('/admin/outcomes', name: 'patrol_list_all_outcomes', defaults: ['withArchived' => true], methods: ['GET'])]
    public function __invoke(bool $withArchived = false): JsonResponse
    {
        return new JsonResponse(($this->handler)(new ListOutcomes($withArchived)));
    }
}
