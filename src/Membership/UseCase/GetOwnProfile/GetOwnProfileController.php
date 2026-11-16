<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetOwnProfile;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetOwnProfileController
{
    public function __construct(
        private GetOwnProfileHandler $handler,
    ) {
    }

    #[Route('/me', name: 'membership_get_own_profile', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetOwnProfile()));
    }
}
