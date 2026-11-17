<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mcp;

use App\Shared\Infrastructure\Http\Problem;
use App\Shared\Infrastructure\Http\ProblemResponses;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Exception\ExceptionInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Override;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turns a failed tool call into a tool result with isError, carrying the same problem as REST would answer: code,
 * translated title, field errors and details. The model reads why a call failed and can correct it. Without this the
 * SDK answers every exception as an internal error. The SDK's own failures, such as an argument of the wrong type,
 * pass through to the SDK.
 */
final readonly class ProblemReferenceHandler implements ReferenceHandlerInterface
{
    public function __construct(
        private ReferenceHandlerInterface $inner,
        private ProblemResponses $problems,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Override]
    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if (!$reference instanceof ToolReference) {
            return $this->inner->handle($reference, $arguments);
        }

        try {
            return $this->inner->handle($reference, $arguments);
        } catch (ExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            $problem = Problem::of($throwable);
            if (500 <= $problem->status) {
                $this->logger->error('MCP tool {tool} failed', ['tool' => $reference->tool->name, 'exception' => $throwable]);
            }

            $body = $this->problems->body($problem->status, $problem->code, $problem->violations, $problem->details);

            return new CallToolResult(
                [new TextContent(\json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE))],
                isError: true,
                structuredContent: $body,
            );
        }
    }
}
