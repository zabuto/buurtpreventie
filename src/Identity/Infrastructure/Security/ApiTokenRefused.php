<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\TokenRefusal;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Carries the reason to the failure handler, which logs it; the message stays general, so nothing that renders an
 * exception can tell the client why.
 */
final class ApiTokenRefused extends AuthenticationException
{
    public function __construct(
        public readonly TokenRefusal $refusal,
    ) {
        parent::__construct('The API token was refused.');
    }
}
