<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use Deprecated;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A member as the token storage holds them: the id and the roles, nothing that needs erasing.
 */
final readonly class MemberUser implements UserInterface
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public Uuid $memberId,
        public array $roles,
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
    #[Deprecated]
    public function eraseCredentials(): void
    {
    }
}
