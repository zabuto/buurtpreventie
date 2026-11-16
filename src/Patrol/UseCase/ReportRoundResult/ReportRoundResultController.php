<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ReportRoundResult;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ReportRoundResultController
{
    public function __construct(
        private ReportRoundResultHandler $handler,
    ) {
    }

    /**
     * The round comes from the path: the body needs no round id, and one it carries counts for nothing.
     */
    #[Route('/rounds/{roundId}/reports', name: 'patrol_report_round_result', requirements: ['roundId' => Requirement::UUID], methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'The id of the new report.',
        content: new OA\JsonContent(ref: new Model(type: ReportRoundResultOutput::class)),
    )]
    public function __invoke(
        string $roundId,
        #[MapRequestPayload(serializationContext: ['default_constructor_arguments' => [ReportRoundResult::class => ['roundId' => '']]])]
        ReportRoundResult $input,
    ): JsonResponse {
        return new JsonResponse(
            ($this->handler)(new ReportRoundResult($roundId, $input->outcomeId, $input->note)),
            Response::HTTP_CREATED,
        );
    }
}
