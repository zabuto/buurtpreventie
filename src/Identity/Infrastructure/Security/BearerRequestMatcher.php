<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * Sends every API request that carries an Authorization header to the token firewall, whatever its scheme, so a
 * wrong or broken header is refused and logged there instead of passing as a browser request without a session.
 */
final readonly class BearerRequestMatcher implements RequestMatcherInterface
{
    #[Override]
    public function matches(Request $request): bool
    {
        return \str_starts_with($request->getPathInfo(), '/api/') && $request->headers->has('Authorization');
    }
}
