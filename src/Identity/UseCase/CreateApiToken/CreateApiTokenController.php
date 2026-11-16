<?php

declare(strict_types=1);

namespace App\Identity\UseCase\CreateApiToken;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class CreateApiTokenController
{
    public function __construct(
        private CreateApiTokenHandler $handler,
    ) {
    }

    /**
     * Always for the member acting: a member id in the body counts for nothing.
     */
    #[Route('/me/tokens', name: 'identity_create_api_token', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'The new token; its secret is in this answer only.',
        content: new OA\JsonContent(ref: new Model(type: CreateApiTokenOutput::class)),
    )]
    public function __invoke(#[MapRequestPayload] CreateApiToken $input): JsonResponse
    {
        return new JsonResponse(
            ($this->handler)(new CreateApiToken($input->name, $input->scopes, $input->expiresAt)),
            Response::HTTP_CREATED,
        );
    }
}
