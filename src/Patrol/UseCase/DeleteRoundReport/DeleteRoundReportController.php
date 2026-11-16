<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRoundReport;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class DeleteRoundReportController
{
    public function __construct(
        private DeleteRoundReportHandler $handler,
    ) {
    }

    #[Route('/reports/{reportId}', name: 'patrol_delete_round_report', requirements: ['reportId' => Requirement::UUID], methods: ['DELETE'])]
    public function __invoke(string $reportId): Response
    {
        ($this->handler)(new DeleteRoundReport($reportId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
