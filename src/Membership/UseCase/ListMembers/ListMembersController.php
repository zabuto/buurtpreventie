<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ListMembers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListMembersController
{
    public function __construct(
        private ListMembersHandler $handler,
    ) {
    }

    #[Route('/admin/members', name: 'membership_list_members', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListMembers $input = new ListMembers(),
    ): JsonResponse {
        return new JsonResponse(($this->handler)($input));
    }
}
