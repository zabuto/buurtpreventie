<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns every failure under /api/ and on the MCP server into application/problem+json (RFC 9457). The title is translated and the body
 * never carries the exception message: that holds ids and internals, and goes to the log only. Runs after the
 * security listener, which answers missing authentication itself, and before Symfony's error page.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -32)]
final readonly class ProblemResponder
{
    private const string API_PREFIX = '/api/';
    private const string MCP_PATH = '/mcp';

    public function __construct(
        private ProblemResponses $problems,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!\str_starts_with($path, self::API_PREFIX) && self::MCP_PATH !== $path) {
            return;
        }

        $problem = Problem::of($event->getThrowable());

        $event->setResponse($this->problems->create(
            $problem->status,
            $problem->code,
            $problem->headers,
            $problem->violations,
            $problem->details,
        ));
    }
}
