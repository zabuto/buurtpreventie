<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetPassword
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
        #[Assert\Length(min: 12, max: 4096)]
        #[SensitiveParameter]
        public string $password,
    ) {
    }
}
