<?php

declare(strict_types=1);

namespace App\Membership\UseCase\InviteMember;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class InviteMemberController
{
    public function __construct(
        private InviteMemberHandler $handler,
    ) {
    }

    /**
     * The secret is in the answer only for an admin inviting a member who never logged in, to pass on by hand when no
     * mail arrives; the frontend makes the link of it.
     */
    #[Route('/admin/members/{memberId}/invitation', name: 'membership_invite_member', requirements: ['memberId' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $memberId): JsonResponse
    {
        return new JsonResponse(($this->handler)(new InviteMember($memberId)), Response::HTTP_CREATED);
    }
}
