<?php

declare(strict_types=1);

namespace App\Membership\UseCase\AddFirstAdmin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddFirstAdmin
{
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
    ) {
    }
}
