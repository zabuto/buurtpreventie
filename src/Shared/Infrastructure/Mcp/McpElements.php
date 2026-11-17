<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mcp;

use Mcp\Exception\ServiceNotFoundException;
use Override;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * The tools, prompts and resources of the MCP server by class, for the SDK to call. The bundle keeps its own lookup
 * private, so the reference handler this app wraps gets this one.
 */
final readonly class McpElements implements ContainerInterface
{
    /** @var list<ContainerInterface> */
    private array $kinds;

    public function __construct(
        #[AutowireLocator('mcp.tool')]
        ContainerInterface $tools,
        #[AutowireLocator('mcp.prompt')]
        ContainerInterface $prompts,
        #[AutowireLocator('mcp.resource')]
        ContainerInterface $resources,
    ) {
        $this->kinds = [$tools, $prompts, $resources];
    }

    #[Override]
    public function get(string $id): mixed
    {
        foreach ($this->kinds as $kind) {
            if ($kind->has($id)) {
                return $kind->get($id);
            }
        }

        throw new ServiceNotFoundException(\sprintf('No MCP element "%s".', $id));
    }

    #[Override]
    public function has(string $id): bool
    {
        return \array_any($this->kinds, static fn(ContainerInterface $kind): bool => $kind->has($id));
    }
}
