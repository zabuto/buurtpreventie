<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMemberSummaries;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetMemberSummariesController
{
    public function __construct(
        private GetMemberSummariesHandler $handler,
    ) {
    }

    #[Route('/members', name: 'membership_get_member_summaries', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        GetMemberSummaries $input = new GetMemberSummaries(),
    ): JsonResponse {
        return new JsonResponse(($this->handler)($input));
    }
}
