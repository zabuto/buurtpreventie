<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetOwnProfile;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 200,
        description: 'The profile of the member logged in.',
        content: new OA\JsonContent(ref: new Model(type: GetOwnProfileOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new GetOwnProfile()));
    }
}
