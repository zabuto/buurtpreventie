<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RevokeMemberApiTokens;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RevokeMemberApiTokens
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
