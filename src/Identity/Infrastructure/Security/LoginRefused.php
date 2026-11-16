<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Uid\Uuid;

/**
 * Carries why a login failed, and for which member, to the failure handler, which logs and records it. The message
 * stays general, so nothing that renders an exception can tell the client why.
 */
final class LoginRefused extends AuthenticationException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?Uuid $memberId = null,
    ) {
        parent::__construct('The login was refused.');
    }
}
