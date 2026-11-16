<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ReactivateMember;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ReactivateMemberController
{
    public function __construct(
        private ReactivateMemberHandler $handler,
    ) {
    }

    #[Route(
        '/admin/members/{memberId}/deactivation',
        name: 'membership_reactivate_member',
        requirements: ['memberId' => Requirement::UUID],
        methods: ['DELETE'],
    )]
    #[OA\Response(response: 204, description: 'Reactivated.')]
    public function __invoke(string $memberId): Response
    {
        ($this->handler)(new ReactivateMember($memberId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
