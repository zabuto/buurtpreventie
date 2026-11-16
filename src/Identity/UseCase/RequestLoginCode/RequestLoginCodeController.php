<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RequestLoginCode;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class RequestLoginCodeController
{
    public const string ROUTE = 'identity_request_login_code';

    public function __construct(
        private RequestLoginCodeHandler $handler,
    ) {
    }

    #[Route('/auth/login-code', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RequestLoginCode $input): JsonResponse
    {
        return new JsonResponse(($this->handler)($input), Response::HTTP_ACCEPTED);
    }
}
