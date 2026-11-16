<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ArchiveOutcome;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ArchiveOutcomeController
{
    public function __construct(
        private ArchiveOutcomeHandler $handler,
    ) {
    }

    #[Route('/admin/outcomes/{outcomeId}/archive', name: 'patrol_archive_outcome', requirements: ['outcomeId' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $outcomeId): Response
    {
        ($this->handler)(new ArchiveOutcome($outcomeId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
