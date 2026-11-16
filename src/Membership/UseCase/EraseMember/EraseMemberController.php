<?php

declare(strict_types=1);

namespace App\Membership\UseCase\EraseMember;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class EraseMemberController
{
    public function __construct(
        private EraseMemberHandler $handler,
    ) {
    }

    #[Route('/admin/members/{memberId}', name: 'membership_erase_member', requirements: ['memberId' => Requirement::UUID], methods: ['DELETE'])]
    public function __invoke(string $memberId): Response
    {
        ($this->handler)(new EraseMember($memberId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
