<?php

declare(strict_types=1);

namespace App\Membership\UseCase\DeactivateMember;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeactivateMember
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
