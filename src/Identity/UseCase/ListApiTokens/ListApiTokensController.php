<?php

declare(strict_types=1);

namespace App\Identity\UseCase\ListApiTokens;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Response(
        response: 200,
        description: 'The own tokens that are not revoked, without their secrets.',
        content: new OA\JsonContent(ref: new Model(type: ListApiTokensOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new ListApiTokens()));
    }
}
