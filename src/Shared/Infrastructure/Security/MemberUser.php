<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use Deprecated;
use Override;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A member as the token storage holds them: the id and the roles, nothing that needs erasing. Two of them are the
 * same user when the member is: the session then keeps a member whose roles changed, with the roles read afresh,
 * instead of logging them out. The credentials version is signed into every "stay logged in" cookie and compared on
 * every request, so a new password ends every cookie and every session; the session that set it takes the new
 * version along.
 */
final readonly class MemberUser implements UserInterface, EquatableInterface
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public Uuid $memberId,
        public array $roles,
        public string $credentialsVersion = '',
    ) {
    }

    public static function of(Actor $actor): self
    {
        return new self($actor->memberId, $actor->roles);
    }

    public function actor(): Actor
    {
        return new Actor($this->memberId, $this->roles);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return \array_map(static fn(Role $role): string => 'ROLE_' . \mb_strtoupper($role->value), $this->roles);
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->memberId->toRfc4122();
    }

    #[Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return (
            $user instanceof self
                && $user->memberId->equals($this->memberId)
                && $user->credentialsVersion === $this->credentialsVersion
        );
    }

    /**
     * No #[Override]: Symfony 8 drops this method from UserInterface, and the attribute would make that a fatal error.
     */
    #[Deprecated]
    public function eraseCredentials(): void
    {
    }
}
