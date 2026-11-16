<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * How a member proved who they are; the audit log records it with every login and failed attempt.
 */
enum LoginMethod: string
{
    case Password = 'password';
    case Code = 'code';
    case Invitation = 'invitation';
}
