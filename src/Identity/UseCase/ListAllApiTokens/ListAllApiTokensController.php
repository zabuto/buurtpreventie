<?php

declare(strict_types=1);

namespace App\Identity\UseCase\ListAllApiTokens;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListAllApiTokensController
{
    public function __construct(
        private ListAllApiTokensHandler $handler,
    ) {
    }

    #[Route('/admin/api-tokens', name: 'identity_list_all_api_tokens', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Every token that is neither revoked nor expired, of every member, without its secret.',
        content: new OA\JsonContent(ref: new Model(type: ListAllApiTokensOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new ListAllApiTokens()));
    }
}
