<?php

declare(strict_types=1);

namespace App\Membership\UseCase\InviteMember;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InviteMember
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
