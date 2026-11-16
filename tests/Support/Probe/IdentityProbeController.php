<?php

declare(strict_types=1);

namespace App\Tests\Support\Probe;

use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Route;

/**
 * Answers who the request runs as, with which roles, and through which channel, so authentication is tested apart from any use case.
 * Only the in-memory test kernel routes to it.
 */
final readonly class IdentityProbeController
{
    public function __construct(
        private ExecutionContext $executionContext,
    ) {
    }

    /**
     * @return array<string, Route>
     */
    public static function routes(): array
    {
        $controller = ['_controller' => self::class];

        return [
            'test_probe_identity' => new Route('/api/v1/_probe-identity', $controller, methods: ['GET', 'POST']),
            'test_probe_admin_identity' => new Route('/api/v1/admin/_probe-identity', $controller, methods: ['GET', 'POST']),
        ];
    }

    public function __invoke(): JsonResponse
    {
        $actor = $this->executionContext->actor();

        return new JsonResponse([
            'memberId' => $actor?->memberId->toRfc4122(),
            'roles' => \array_map(static fn(Role $role): string => $role->value, $actor->roles ?? []),
            'channel' => $this->executionContext->channel()->value,
        ]);
    }
}
