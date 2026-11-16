<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RestoreOutcome;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class RestoreOutcomeController
{
    public function __construct(
        private RestoreOutcomeHandler $handler,
    ) {
    }

    #[Route('/admin/outcomes/{outcomeId}/archive', name: 'patrol_restore_outcome', requirements: ['outcomeId' => Requirement::UUID], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Restored, also when it was not archived.')]
    public function __invoke(string $outcomeId): Response
    {
        ($this->handler)(new RestoreOutcome($outcomeId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
