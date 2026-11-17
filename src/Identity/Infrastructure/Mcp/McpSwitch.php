<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mcp;

use App\Settings\UseCase\GetMcpAccess\GetMcpAccess;
use App\Settings\UseCase\GetMcpAccess\GetMcpAccessHandler;
use App\Shared\Infrastructure\Http\ProblemResponses;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers every request to the MCP server with 404 while the association keeps MCP off. Runs before the firewall, so
 * a token is neither looked up nor marked as used, and an association without MCP shows no sign of one.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final readonly class McpSwitch
{
    public const string PATH = '/mcp';

    public function __construct(
        private GetMcpAccessHandler $access,
        private ProblemResponses $problems,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !self::isMcp($event->getRequest()->getPathInfo())) {
            return;
        }

        if (($this->access)(new GetMcpAccess())->allowed) {
            return;
        }

        $event->setResponse($this->problems->create(Response::HTTP_NOT_FOUND, 'not_found'));
    }

    public static function isMcp(string $path): bool
    {
        return self::PATH === $path || \str_starts_with($path, self::PATH . '/');
    }
}
