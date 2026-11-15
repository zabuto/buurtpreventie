<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class WithdrawMemberFromUpcomingRounds
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
