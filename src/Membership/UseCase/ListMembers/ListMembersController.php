<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ListMembers;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 200,
        description: 'Every member that is not erased, deactivated ones included.',
        content: new OA\JsonContent(ref: new Model(type: ListMembersOutput::class)),
    )]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListMembers $input = new ListMembers(),
    ): JsonResponse {
        return new JsonResponse(($this->handler)($input));
    }
}
