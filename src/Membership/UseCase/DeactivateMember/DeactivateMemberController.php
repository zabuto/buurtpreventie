<?php

declare(strict_types=1);

namespace App\Membership\UseCase\DeactivateMember;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class DeactivateMemberController
{
    public function __construct(
        private DeactivateMemberHandler $handler,
    ) {
    }

    #[Route('/admin/members/{memberId}/deactivation', name: 'membership_deactivate_member', requirements: ['memberId' => Requirement::UUID], methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Deactivated.')]
    public function __invoke(string $memberId): Response
    {
        ($this->handler)(new DeactivateMember($memberId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
