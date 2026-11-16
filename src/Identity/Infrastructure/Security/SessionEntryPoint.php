<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Http\ProblemResponses;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Answers a request without a session on an endpoint that needs one, and logs it, so probing shows in the log.
 */
final readonly class SessionEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ProblemResponses $problems,
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        $this->logger->notice('Request without a session refused', ['path' => $request->getPathInfo()]);

        return $this->problems->create(Response::HTTP_UNAUTHORIZED, 'unauthorized');
    }
}
