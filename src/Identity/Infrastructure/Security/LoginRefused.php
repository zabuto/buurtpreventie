<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Uid\Uuid;

/**
 * Carries why a login failed, for which member, and what the client may know, to the failure handler, which logs,
 * records and answers it. The message stays general, so nothing that renders an exception can tell the client why.
 */
final class LoginRefused extends AuthenticationException
{
    /**
     * @param string $reason for the log only
     * @param string $problem the problem code the client gets
     * @param int|null $attemptsLeft for a wrong login code, so the member knows how many tries remain
     * @param string|null $blankField a required field left empty, answered as invalid input instead of a failed login
     */
    public function __construct(
        public readonly string $reason,
        public readonly ?Uuid $memberId = null,
        public readonly string $problem = 'login_failed',
        public readonly ?int $attemptsLeft = null,
        public readonly ?string $blankField = null,
    ) {
        parent::__construct('The login was refused.');
    }
}
