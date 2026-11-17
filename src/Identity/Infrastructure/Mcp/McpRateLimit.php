<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mcp;

use App\Identity\Infrastructure\Security\ApiTokenAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Holds every token to a number of requests per minute on the MCP server, right after the firewall, so a refused
 * token never counts. The key is the id of the token, never its secret.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class McpRateLimit
{
    public function __construct(
        #[Target('mcp_per_token')]
        private RateLimiterFactoryInterface $limiter,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @throws TooManyRequestsHttpException with the seconds until the next request is accepted
     */
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !McpSwitch::isMcp($event->getRequest()->getPathInfo())) {
            return;
        }

        $tokenId = $this->tokenStorage->getToken()?->getAttributes()[ApiTokenAuthenticator::TOKEN_ID] ?? null;
        if (!\is_string($tokenId)) {
            return;
        }

        $limit = $this->limiter->create($tokenId)->consume();
        if ($limit->isAccepted()) {
            return;
        }

        throw new TooManyRequestsHttpException(\max(1, $limit->getRetryAfter()->getTimestamp() - \time()));
    }
}
