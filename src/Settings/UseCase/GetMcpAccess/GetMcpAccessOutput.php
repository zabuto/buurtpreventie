<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetMcpAccess;

final readonly class GetMcpAccessOutput
{
    /**
     * @param bool $allowed false makes the MCP server unreachable, over HTTP and STDIO
     * @param list<string> $roles the roles a member keeps on the MCP server, by their value
     */
    public function __construct(
        public bool $allowed,
        public array $roles,
    ) {
    }
}
