<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Refuses every request with an API token under /api/v1/me/tokens, whatever its scopes: tokens are managed in the
 * browser only, so a leaked token can neither see the others nor revoke them.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class TokenManagementGuard
{
    private const string PATH = '/api/v1/me/tokens';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (self::PATH !== $path && !\str_starts_with($path, self::PATH . '/')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token || !$token->hasAttribute(ApiTokenAuthenticator::SCOPES)) {
            return;
        }

        throw new BrowserOnly();
    }
}
