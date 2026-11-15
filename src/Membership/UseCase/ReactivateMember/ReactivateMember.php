<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ReactivateMember;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReactivateMember
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
