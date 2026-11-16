<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMember;

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
    public function __invoke(string $memberId): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetMember($memberId)));
    }
}
