<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMember;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetMember
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
