<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMemberRoles;

use App\Shared\Domain\Role;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeMemberRoles
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
        #[Assert\All([new Assert\Choice(callback: [Role::class, 'values'])])]
        public array $roles,
    ) {
    }
}
