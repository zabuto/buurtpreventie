<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mcp;

use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Server;
use Mcp\Server\Builder;

/**
 * Builds the MCP server of the bundle with the reference handler of this app, which checks the scope of the token and
 * reports a failed tool call as a problem (config/services.yaml).
 */
final readonly class McpServerFactory
{
    public static function build(Builder $builder, ReferenceHandlerInterface $references): Server
    {
        return $builder->setReferenceHandler($references)->build();
    }
}
