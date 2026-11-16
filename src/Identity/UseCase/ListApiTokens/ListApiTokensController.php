<?php

declare(strict_types=1);

namespace App\Identity\UseCase\ListApiTokens;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListApiTokensController
{
    public function __construct(
        private ListApiTokensHandler $handler,
    ) {
    }

    #[Route('/me/tokens', name: 'identity_list_api_tokens', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new ListApiTokens()));
    }
}
