<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListOwnActivity;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListOwnActivityController
{
    public function __construct(
        private ListOwnActivityHandler $handler,
    ) {
    }

    #[Route('/me/activity', name: 'audit_list_own_activity', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListOwnActivity $input = new ListOwnActivity(),
    ): JsonResponse {
        return new JsonResponse(($this->handler)($input));
    }
}
