<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use RuntimeException;

/**
 * The current actor may not perform the action. Entry points turn it into their own refusal, such as HTTP 403.
 */
final class AccessDenied extends RuntimeException
{
    public static function forAction(string $action): self
    {
        return new self(\sprintf('Access denied for "%s".', $action));
    }
}
