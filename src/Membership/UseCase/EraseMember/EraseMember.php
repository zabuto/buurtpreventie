<?php

declare(strict_types=1);

namespace App\Membership\UseCase\EraseMember;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EraseMember
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
