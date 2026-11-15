<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Deprecated;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The app acting on its own, such as the scheduler or the installer. It is no member, so it counts nowhere as a
 * person and never gets member rights; a voter allows a system action only to ROLE_SYSTEM, which no request without a
 * token has.
 */
final readonly class SystemUser implements UserInterface
{
    public const string ROLE = 'ROLE_SYSTEM';

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return [self::ROLE];
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return 'system';
    }

    #[Override]
    #[Deprecated]
    public function eraseCredentials(): void
    {
    }
}
