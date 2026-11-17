<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\TokenScope;
use App\Identity\Infrastructure\Mcp\McpSwitch;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Holds a request with an API token to the scopes of that token, right after the firewall: read for reading, write
 * for every change, admin for everything under /api/v1/admin/. The voters decide on the roles of the member on top of
 * this, so scope and role must both allow an action. A browser session has no scopes and passes. Every request to the
 * MCP server is a POST, so there read lets a token in and the tool it calls decides on write (McpScopes).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class TokenScopeGuard
{
    private const string ADMIN_PREFIX = '/api/v1/admin/';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token || !$token->hasAttribute(ApiTokenAuthenticator::SCOPES)) {
            return;
        }

        $required = self::requiredScope($event->getRequest());
        if (\in_array($required, ApiTokenAuthenticator::scopesOf($token), strict: true)) {
            return;
        }

        throw new InsufficientScope();
    }

    public static function requiredScope(Request $request): TokenScope
    {
        return match (true) {
            \str_starts_with($request->getPathInfo(), self::ADMIN_PREFIX) => TokenScope::Admin,
            $request->isMethodSafe() || McpSwitch::isMcp($request->getPathInfo()) => TokenScope::Read,
            default => TokenScope::Write,
        };
    }
}
