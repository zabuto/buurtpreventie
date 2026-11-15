<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\WithdrawFromRound;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class WithdrawFromRound
{
    /**
     * @param string|null $memberId the member to withdraw; the actor when null
     */
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
        #[Assert\Uuid]
        public ?string $memberId = null,
    ) {
    }
}
