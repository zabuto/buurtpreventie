<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ResetMcpAllowed;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ResetMcpAllowedController
{
    public function __construct(
        private ResetMcpAllowedHandler $handler,
    ) {
    }

    #[Route('/admin/members/mcp-allowed/reset', name: 'membership_reset_mcp_allowed', methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'MCP is switched off for every picked member; only mcpRoles counts.',
        content: new OA\JsonContent(ref: new Model(type: ResetMcpAllowedOutput::class)),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(($this->handler)(new ResetMcpAllowed()));
    }
}
