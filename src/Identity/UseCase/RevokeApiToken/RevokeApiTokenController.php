<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RevokeApiToken;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class RevokeApiTokenController
{
    public function __construct(
        private RevokeApiTokenHandler $handler,
    ) {
    }

    #[Route('/me/tokens/{tokenId}', name: 'identity_revoke_api_token', requirements: ['tokenId' => Requirement::UUID], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Revoked.')]
    public function __invoke(string $tokenId): Response
    {
        ($this->handler)(new RevokeApiToken($tokenId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
