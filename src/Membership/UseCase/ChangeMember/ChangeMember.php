<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMember;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeMember
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
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
        public ?string $phone,
        #[Assert\Length(max: 255)]
        public ?string $address,
        public bool $certified,
    ) {
    }
}
