<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Takes a changing request on the browser firewall only from the frontend on the same origin, the login included, so
 * another site cannot act with the member's session cookie. Symfony's same-origin manager decides: Sec-Fetch-Site,
 * else Origin or Referer, else the csrf-token header the frontend sets for browsers that send neither, such as Safari
 * on iOS 15. Runs before the firewall, so a refused login never reaches the password check. A request with an API
 * token carries no cookie and goes to the token firewall unchecked.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
final readonly class SameOriginGuard
{
    public const string TOKEN_ID = 'api';
    private const string HEADER = 'csrf-token';

    public function __construct(
        #[Autowire(service: 'security.csrf.same_origin_token_manager')]
        private CsrfTokenManagerInterface $csrfTokens,
        private BearerRequestMatcher $tokenRequests,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (
            !$event->isMainRequest()
            || $request->isMethodSafe()
            || !\str_starts_with($request->getPathInfo(), '/api/')
            || $this->tokenRequests->matches($request)
        ) {
            return;
        }

        $value = $request->headers->get(self::HEADER) ?? $this->csrfTokens->getToken(self::TOKEN_ID)->getValue();
        if (!$this->csrfTokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $value))) {
            throw new CsrfCheckFailed();
        }
    }
}
