<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SummariseRoundReports;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SummariseRoundReportsController
{
    public function __construct(
        private SummariseRoundReportsHandler $handler,
    ) {
    }

    #[Route('/reports/summary', name: 'patrol_summarise_round_reports', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The reports of the period counted per outcome, and the incidents per month.',
        content: new OA\JsonContent(ref: new Model(type: SummariseRoundReportsOutput::class)),
    )]
    public function __invoke(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] SummariseRoundReports $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input));
    }
}
