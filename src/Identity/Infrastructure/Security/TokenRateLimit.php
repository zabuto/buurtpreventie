<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Mcp\McpSwitch;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Holds every request with an API token, on /api/v1 and /mcp, to the limits of that token, right after the firewall
 * and the scope check, so a refused token never counts. A browser session does not count. Every request to the MCP
 * server is a read: which tool it calls is known only to ScopedReferenceHandler, which counts a write there.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class TokenRateLimit
{
    public function __construct(
        private TokenLimits $limits,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $tokenId = ApiTokenAuthenticator::tokenIdOf($this->tokenStorage->getToken());
        if (null === $tokenId) {
            return;
        }

        if (self::isRead($event->getRequest())) {
            $this->limits->read($tokenId);

            return;
        }

        $this->limits->write($tokenId);
    }

    private static function isRead(Request $request): bool
    {
        return $request->isMethodSafe() || McpSwitch::isMcp($request->getPathInfo());
    }
}
