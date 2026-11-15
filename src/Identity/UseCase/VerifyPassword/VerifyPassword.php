<?php

declare(strict_types=1);

namespace App\Identity\UseCase\VerifyPassword;

use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VerifyPassword
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
        #[SensitiveParameter]
        public string $password,
    ) {
    }
}
