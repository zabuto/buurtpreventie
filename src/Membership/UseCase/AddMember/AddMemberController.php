<?php

declare(strict_types=1);

namespace App\Membership\UseCase\AddMember;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AddMemberController
{
    public function __construct(
        private AddMemberHandler $handler,
    ) {
    }

    #[Route('/admin/members', name: 'membership_add_member', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] AddMember $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input), Response::HTTP_CREATED);
    }
}
