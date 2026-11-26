<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RevokeMemberApiTokens;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class RevokeMemberApiTokensController
{
    public function __construct(
        private RevokeMemberApiTokensHandler $handler,
    ) {
    }

    #[Route(
        '/admin/members/{memberId}/api-tokens/revoke',
        name: 'identity_revoke_member_api_tokens',
        requirements: ['memberId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[OA\Response(response: 204, description: 'Every token of the member is revoked; a member without tokens gives the same answer.')]
    public function __invoke(string $memberId): Response
    {
        ($this->handler)(new RevokeMemberApiTokens($memberId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
