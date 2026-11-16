<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMember;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class GetMemberController
{
    public function __construct(
        private GetMemberHandler $handler,
    ) {
    }

    #[Route('/admin/members/{memberId}', name: 'membership_get_member', requirements: ['memberId' => Requirement::UUID], methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The member with everything the admin manages.',
        content: new OA\JsonContent(ref: new Model(type: GetMemberOutput::class)),
    )]
    public function __invoke(string $memberId): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetMember($memberId)));
    }
}
