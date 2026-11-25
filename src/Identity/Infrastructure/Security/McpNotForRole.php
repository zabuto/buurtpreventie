<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * A valid token of a member who has none of the roles the association allows on the MCP server. The member hears why,
 * unlike with a refused token: the token itself is fine.
 */
final class McpNotForRole extends AuthenticationException
{
    public function __construct()
    {
        parent::__construct('MCP is closed to the roles of this member.');
    }
}
