<?php

declare(strict_types=1);

namespace App\Membership\UseCase\AddMember;

use App\Shared\Domain\Role;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddMember
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $displayName,
        #[Assert\Length(max: 30)]
        public ?string $phone = null,
        #[Assert\Length(max: 255)]
        public ?string $address = null,
        #[Assert\All([new Assert\Choice(callback: [Role::class, 'values'])])]
        public array $roles = [],
    ) {
    }
}
