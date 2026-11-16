<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use RuntimeException;

/**
 * The action needs a recent proof of who the member is (RecentAuthentication), and the session has none. Entry points
 * turn it into their own refusal, such as HTTP 403 with a code the frontend answers by asking for the password.
 */
final class ReauthenticationRequired extends RuntimeException
{
    public static function forAction(string $action): self
    {
        return new self(\sprintf('"%s" needs a recent login.', $action));
    }
}
