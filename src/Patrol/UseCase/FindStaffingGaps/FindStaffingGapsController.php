<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\FindStaffingGaps;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 200,
        description: 'The rounds that have not started and seek a buddy.',
        content: new OA\JsonContent(ref: new Model(type: FindStaffingGapsOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new FindStaffingGaps()));
    }
}
