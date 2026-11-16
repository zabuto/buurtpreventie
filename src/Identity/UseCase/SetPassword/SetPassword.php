<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetPassword
{
    public function __construct(
        #[Assert\Length(min: 12, max: 4096)]
        #[SensitiveParameter]
        public string $password,
        /** Empty for the member acting. */
        #[Assert\Uuid]
        public ?string $memberId = null,
    ) {
    }
}
